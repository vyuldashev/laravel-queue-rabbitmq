<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue;

use DateTime;
use ErrorException;
use Exception;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;

class RabbitMQQueue extends Queue implements QueueContract
{
    /**
     * Used for retry logic, to set the retries on the message metadata instead of the message body.
     */
    const ATTEMPT_COUNT_HEADERS_KEY = 'attempts_count';

    protected $connection;
    protected $channel;

    protected $declareExchange;
    protected $declaredExchanges = [];
    protected $declareBindQueue;
    protected $sleepOnError;

    protected $declaredQueues = [];

    protected $defaultQueue;
    protected $configQueue;
    protected $configExchange;

    /**
     * @var int
     */
    private $retryAfter;

    /**
     * @var string
     */
    private $correlationId;

    /**
     * @param AMQPStreamConnection $amqpConnection
     * @param array                $config
     */
    public function __construct(AMQPStreamConnection $amqpConnection, $config)
    {
        $this->connection = $amqpConnection;
        $this->defaultQueue = $config['queue'];
        $this->configQueue = $config['queue_params'];
        $this->configExchange = $config['exchange_params'];
        $this->declareExchange = $config['exchange_declare'];
        $this->declareBindQueue = $config['queue_declare_bind'];
        $this->sleepOnError = isset($config['sleep_on_error']) ? $config['sleep_on_error'] : 5;

        $this->channel = $this->getChannel();
    }

    /**
     * Get the size of the queue.
     *
     * @param string $queue
     *
     * @return int
     */
    public function size($queue = null)
    {
        // TODO: Implement size() method.
    }

    /**
     * Push a new job onto the queue.
     *
     * @param string $job
     * @param mixed  $data
     * @param string $queue
     *
     * @return bool
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue, []);
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param string $payload
     * @param string $queue
     * @param array  $options
     *
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        try {
            $queue = $this->getQueueName($queue);
            if (isset($options['delay']) && $options['delay'] > 0) {
                list($queue, $exchange) = $this->declareDelayedQueue($queue, $options['delay']);
            } else {
                list($queue, $exchange) = $this->declareQueue($queue);
            }

            $headers = [
                'Content-Type'  => 'application/json',
                'delivery_mode' => 2,
            ];

            if ($this->retryAfter !== null) {
                $headers['application_headers'] = [self::ATTEMPT_COUNT_HEADERS_KEY => ['I', $this->retryAfter]];
            }

            // push job to a queue
            $message = new AMQPMessage($payload, $headers);

            $correlationId = $this->getCorrelationId();
            $message->set('correlation_id', $correlationId);

            // push task to a queue
            $this->channel->basic_publish($message, $exchange, $queue);

            return $correlationId;
        } catch (ErrorException $exception) {
            $this->reportConnectionError('pushRaw', $exception);
        }
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param \DateTime|int $delay
     * @param string        $job
     * @param mixed         $data
     * @param string        $queue
     *
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue, ['delay' => $this->secondsUntil($delay)]);
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param string|null $queue
     *
     * @return \Illuminate\Queue\Jobs\Job|null
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueueName($queue);

        try {
            // declare queue if not exists
            $this->declareQueue($queue);

            // get envelope
            $message = $this->channel->basic_get($queue);

            if ($message instanceof AMQPMessage) {
                return new RabbitMQJob(
                    $this->container,
                    $this,
                    $this->channel,
                    $queue,
                    $message,
                    $this->connectionName
                );
            }
        } catch (ErrorException $exception) {
            $this->reportConnectionError('pop', $exception);
        }
    }

    /**
     * @param string $queue
     *
     * @return string
     */
    private function getQueueName($queue)
    {
        return $queue ?: $this->defaultQueue;
    }

    /**
     * @return AMQPChannel
     */
    private function getChannel()
    {
        return $this->connection->channel();
    }

    /**
     * @param $name
     *
     * @return array
     */
    private function declareQueue($name)
    {
        $name = $this->getQueueName($name);
        $exchange = $this->configExchange['name'] ?: $name;

        if ($this->declareExchange && !in_array($exchange, $this->declaredExchanges, true)) {
            // declare exchange
            $this->channel->exchange_declare(
                $exchange,
                $this->configExchange['type'],
                $this->configExchange['passive'],
                $this->configExchange['durable'],
                $this->configExchange['auto_delete']
            );

            $this->declaredExchanges[] = $exchange;
        }

        if ($this->declareBindQueue && !in_array($name, $this->declaredQueues, true)) {
            // declare queue
            $this->channel->queue_declare(
                $name,
                $this->configQueue['passive'],
                $this->configQueue['durable'],
                $this->configQueue['exclusive'],
                $this->configQueue['auto_delete']
            );

            // bind queue to the exchange
            $this->channel->queue_bind($name, $exchange, $name);

            $this->declaredQueues[] = $name;
        }

        return [$name, $exchange];
    }

    /**
     * @param string       $destination
     * @param DateTime|int $delay
     *
     * @return array
     */
    private function declareDelayedQueue($destination, $delay)
    {
        $delay = $this->secondsUntil($delay);
        $destination = $this->getQueueName($destination);
        $destinationExchange = $this->configExchange['name'] ?: $destination;
        $name = $this->getQueueName($destination).'_deferred_'.$delay;
        $exchange = $this->configExchange['name'] ?: $destination;

        // declare exchange
        if (!in_array($exchange, $this->declaredExchanges, true)) {
            $this->channel->exchange_declare(
                $exchange,
                $this->configExchange['type'],
                $this->configExchange['passive'],
                $this->configExchange['durable'],
                $this->configExchange['auto_delete']
            );
        }

        // declare queue
        if (!in_array($name, $this->declaredQueues, true)) {
            $this->channel->queue_declare(
                $name,
                $this->configQueue['passive'],
                $this->configQueue['durable'],
                $this->configQueue['exclusive'],
                $this->configQueue['auto_delete'],
                false,
                new AMQPTable([
                    'x-dead-letter-exchange'    => $destinationExchange,
                    'x-dead-letter-routing-key' => $destination,
                    'x-message-ttl'             => $delay * 1000,
                ])
            );
        }

        // bind queue to the exchange
        $this->channel->queue_bind($name, $exchange, $name);

        return [$name, $exchange];
    }

    /**
     * Sets the attempts member variable to be used in message generation.
     *
     * @param int $count
     *
     * @return void
     */
    public function setAttempts($count)
    {
        $this->retryAfter = $count;
    }

    /**
     * Sets the correlation id for a message to be published.
     *
     * @param string $id
     *
     * @return void
     */
    public function setCorrelationId($id)
    {
        $this->correlationId = $id;
    }

    /**
     * Retrieves the correlation id, or a unique id.
     *
     * @return string
     */
    public function getCorrelationId()
    {
        return $this->correlationId ?: uniqid('', true);
    }

    /**
     * @param string    $action
     * @param Exception $e
     */
    private function reportConnectionError($action, Exception $e)
    {
        Log::error('AMQP error while attempting '.$action.': '.$e->getMessage());
        // Sleep so that we don't flood the log file
        sleep($this->sleepOnError);
    }
}
