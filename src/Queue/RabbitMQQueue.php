<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Log;
use RuntimeException;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;

class RabbitMQQueue extends Queue implements QueueContract
{
    /**
     * Used for retry logic, to set the retries on the message metadata instead of the message body.
     */
    const ATTEMPT_COUNT_HEADERS_KEY = 'attempts_count';

    /**
     * @var AmqpContext
     */
    protected $context;

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

    public function __construct(AmqpContext $context, array $config)
    {
        $this->context = $context;
        $this->defaultQueue = $config['queue'];
        $this->queueParameters = $config['queue_params'];
        $this->queueArguments = isset($this->queueParameters['arguments']) ? json_decode($this->queueParameters['arguments'], true) : [];
        $this->configExchange = $config['exchange_params'];
        $this->declareExchange = $config['exchange_declare'];
        $this->declareBindQueue = $config['queue_declare_bind'];
        $this->sleepOnError = $config['sleep_on_error'] ?? 5;
    }

    /** @inheritdoc */
    public function size($queueName = null): int
    {
        $queue = $this->context->createQueue($this->getQueueName($queueName));
        $queue->addFlag(AmqpQueue::FLAG_PASSIVE);

        return $this->context->declareQueue($queue);
    }

    /** @inheritdoc */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue, []);
    }

    /** @inheritdoc */
    public function pushRaw($payload, $queueName = null, array $options = [])
    {
        try {
            $queueName = $this->getQueueName($queueName);
            list($queueName, $exchangeName) = $this->declareQueue($queueName);

            $topic = $this->context->createTopic($exchangeName);

            $message = $this->context->createMessage($payload);
            $message->setRoutingKey($queueName);
            $message->setCorrelationId($this->getCorrelationId());
            $message->setContentType('application/json');
            $message->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);

            if ($this->retryAfter !== null) {
                $message->setProperty(self::ATTEMPT_COUNT_HEADERS_KEY, $this->retryAfter);
            }

            $producer = $this->context->createProducer();
            if (isset($options['delay']) && $options['delay'] > 0) {
                $producer->setDeliveryDelay($options['delay'] * 1000);
            }

            $producer->send($topic, $message);

            return $message->getCorrelationId();
        } catch (\Exception $exception) {
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
    public function pop($queueName = null)
    {
        $queueName = $this->getQueueName($queueName);

        try {
            // declare queue if not exists
            $this->declareQueue($queueName);


            $queue = $this->context->createQueue($queueName);
            $consumer = $this->context->createConsumer($queue);

            $message = $consumer->receiveNoWait();

            if ($message) {
                return new RabbitMQJob(
                    $this->container,
                    $this,
                    $consumer,
                    $queueName,
                    $message,
                    $this->connectionName
                );
            }
        } catch (\Exception $exception) {
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

    private function declareQueue(string $queueName): array
    {
        $queueName = $this->getQueueName($queueName);
        $exchangeName = $this->configExchange['name'] ?: $queueName;

        if ($this->declareExchange && !in_array($exchangeName, $this->declaredExchanges, true)) {
            $topic = $this->context->createTopic($exchangeName);
            $topic->setType($this->configExchange['type']);

            if ($this->configExchange['passive']) {
                $topic->addFlag(AmqpTopic::FLAG_PASSIVE);
            }
            if ($this->configExchange['durable']) {
                $topic->addFlag(AmqpTopic::FLAG_DURABLE);
            }
            if ($this->configExchange['auto_delete']) {
                $topic->addFlag(AmqpTopic::FLAG_AUTODELETE);
            }

            $this->context->declareTopic($topic);

            $this->declaredExchanges[] = $exchangeName;
        }

        if ($this->declareBindQueue && !in_array($queueName, $this->declaredQueues, true)) {
            $queue = $this->context->createQueue($queueName);


            if ($this->queueParameters['passive']) {
                $queue->addFlag(AmqpQueue::FLAG_PASSIVE);
            }
            if ($this->queueParameters['durable']) {
                $queue->addFlag(AmqpQueue::FLAG_DURABLE);
            }
            if ($this->queueParameters['exclusive']) {
                $queue->addFlag(AmqpQueue::FLAG_EXCLUSIVE);
            }
            if ($this->queueParameters['auto_delete']) {
                $queue->addFlag(AmqpQueue::FLAG_AUTODELETE);
            }

            $queue->setArguments($this->queueArguments);

            $this->context->declareQueue($queue);


            $this->context->bind(new AmqpBind(
                $queue,
                $this->context->createTopic($exchangeName),
                $queueName)
            );

            $this->declaredQueues[] = $queueName;
        }

        return [$queueName, $exchangeName];
    }

    /**
     * @param string $action
     * @param \Exception $e
     * @throws \Exception
     */
    protected function reportConnectionError($action, \Exception $e)
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
