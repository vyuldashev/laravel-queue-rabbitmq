<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Psr\Log\LoggerInterface;
use RuntimeException;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;

class RabbitMQQueue extends Queue implements QueueContract
{
    /**
     * Used for retry logic, to set the retries on the message metadata instead of the message body.
     */
    const ATTEMPT_COUNT_HEADERS_KEY = 'attempts_count';

    protected $declareExchange;
    protected $declareQueue;
    protected $declareBindQueue;
    protected $sleepOnError;

    protected $defaultQueue;
    protected $queueParameters;
    protected $queueArguments;
    protected $configExchange;

    private $declaredExchanges = [];
    private $declaredQueues = [];

    /**
     * @var AmqpContext
     */
    private $context;
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
        $this->declareQueue = $config['queue_declare'];
        $this->declareBindQueue = $config['queue_declare_bind'];
        $this->sleepOnError = $config['sleep_on_error'] ?? 5;
    }

    /** @inheritdoc */
    public function size($queueName = null): int
    {
        /** @var AmqpQueue $queue */
        list($queue) = $this->declareEverything($queueName);

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
            /**
             * @var AmqpTopic $topic
             * @var AmqpQueue $queue
             */
            list($queue, $topic) = $this->declareEverything($queueName);

            $message = $this->context->createMessage($payload);
            $message->setRoutingKey($queue->getQueueName());
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
        try {
            /** @var AmqpQueue $queue */
            list($queue) = $this->declareEverything($queueName);

            $consumer = $this->context->createConsumer($queue);

            if ($message = $consumer->receiveNoWait()) {
                return new RabbitMQJob($this->container, $this, $consumer, $message);
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

    /**
     * @return AmqpContext
     */
    public function getContext(): AmqpContext
    {
        return $this->context;
    }

    /**
     * @param string $queueName
     *
     * @return array [Interop\Amqp\AmqpQueue, Interop\Amqp\AmqpTopic]
     */
    private function declareEverything(string $queueName = null): array
    {
        $queueName = $queueName ?: $this->defaultQueue;
        $exchangeName = $this->configExchange['name'] ?: $queueName;

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

        if ($this->declareExchange && !in_array($exchangeName, $this->declaredExchanges, true)) {
            $this->context->declareTopic($topic);

            $this->declaredExchanges[] = $exchangeName;
        }

        $queue = $this->context->createQueue($queueName);
        $queue->setArguments($this->queueArguments);
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

        if ($this->declareQueue && !in_array($queueName, $this->declaredQueues, true)) {
            $this->context->declareQueue($queue);

            $this->declaredQueues[] = $queueName;
        }

        if ($this->declareBindQueue) {
            $this->context->bind(new AmqpBind($queue, $topic, $queue->getQueueName()));
        }

        return [$queue, $topic];
    }

    /**
     * @param string $action
     * @param \Exception $e
     * @throws \Exception
     */
    protected function reportConnectionError($action, \Exception $e)
    {
        /** @var LoggerInterface $logger */
        $logger = $this->container['log'];

        $logger->error('AMQP error while attempting ' . $action . ': ' . $e->getMessage());

        // If it's set to false, throw an error rather than waiting
        if ($this->sleepOnError === false) {
            throw new RuntimeException('Error writing data to the connection with RabbitMQ', null, $e);
        }

        // Sleep so that we don't flood the log file
        sleep($this->sleepOnError);
    }
}
