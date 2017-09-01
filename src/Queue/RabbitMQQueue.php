<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue;

use ErrorException;
use Exception;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;
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
    protected $declareBindQueue;
    protected $sleepOnError;

    protected $defaultQueue;
    protected $queueParameters;
    protected $queueArguments;
    protected $configExchange;

    private $declaredExchanges = [];
    private $declaredQueues = [];

    private $retryAfter;
    private $correlationId;

    public function __construct(AMQPStreamConnection $connection, array $config)
    {
        $this->connection = $connection;
        $this->defaultQueue = $config['queue'];
        $this->queueParameters = $config['queue_params'];
        $this->queueArguments = isset($this->queueParameters['arguments']) ? json_decode($this->queueParameters['arguments'], true) : [];
        $this->configExchange = $config['exchange_params'];
        $this->declareExchange = $config['exchange_declare'];
        $this->declareBindQueue = $config['queue_declare_bind'];
        $this->sleepOnError = $config['sleep_on_error'] ?? 5;

        $this->channel = $this->getChannel();
    }

    /** @inheritdoc */
    public function size($queue = null): int
    {
        list(, $messageCount) = $this->channel->queue_declare($this->getQueueName($queue), true);

        return $messageCount;
    }

    /** @inheritdoc */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue, []);
    }

    /** @inheritdoc */
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
                'Content-Type' => 'application/json',
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

            return null;
        }
    }

    /** @inheritdoc */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue, ['delay' => $this->secondsUntil($delay)]);
    }

    /** @inheritdoc */
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

        return null;
    }

    /**
     * Sets the attempts member variable to be used in message generation.
     *
     * @param int $count
     *
     * @return void
     */
    public function setAttempts(int $count)
    {
        $this->retryAfter = $count;
    }

    /**
     * Retrieves the correlation id, or a unique id.
     *
     * @return string
     */
    public function getCorrelationId(): string
    {
        return $this->correlationId ?: uniqid('', true);
    }

    /**
     * Sets the correlation id for a message to be published.
     *
     * @param string $id
     *
     * @return void
     */
    public function setCorrelationId(string $id)
    {
        $this->correlationId = $id;
    }

    private function getQueueName(string $queue = null): string
    {
        return $queue ?: $this->defaultQueue;
    }

    private function getChannel(): AMQPChannel
    {
        return $this->connection->channel();
    }

    private function declareQueue(string $name): array
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
                $this->queueParameters['passive'],
                $this->queueParameters['durable'],
                $this->queueParameters['exclusive'],
                $this->queueParameters['auto_delete'],
                false,
                new AMQPTable($this->queueArguments)
            );

            // bind queue to the exchange
            $this->channel->queue_bind($name, $exchange, $name);

            $this->declaredQueues[] = $name;
        }

        return [$name, $exchange];
    }

    private function declareDelayedQueue(string $destination, $delay): array
    {
        $delay = $this->secondsUntil($delay);
        $destination = $this->getQueueName($destination);
        $destinationExchange = $this->configExchange['name'] ?: $destination;
        $name = $this->getQueueName($destination) . '_deferred_' . $delay;
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
            $queueArguments = array_merge([
                'x-dead-letter-exchange' => $destinationExchange,
                'x-dead-letter-routing-key' => $destination,
                'x-message-ttl' => $delay * 1000,
            ], (array)$this->queueArguments);

            $this->channel->queue_declare(
                $name,
                $this->queueParameters['passive'],
                $this->queueParameters['durable'],
                $this->queueParameters['exclusive'],
                $this->queueParameters['auto_delete'],
                false,
                new AMQPTable($queueArguments)
            );
        }

        // bind queue to the exchange
        $this->channel->queue_bind($name, $exchange, $name);

        return [$name, $exchange];
    }

    /**
     * @param string $action
     * @param Exception $e
     * @throws Exception
     */
    protected function reportConnectionError($action, Exception $e)
    {
        Log::error('AMQP error while attempting ' . $action . ': ' . $e->getMessage());

        // If it's set to false, throw an error rather than waiting
        if ($this->sleepOnError === false) {
            throw new RuntimeException('Error writing data to the connection with RabbitMQ');
        }

        // Sleep so that we don't flood the log file
        sleep($this->sleepOnError);
    }
}
