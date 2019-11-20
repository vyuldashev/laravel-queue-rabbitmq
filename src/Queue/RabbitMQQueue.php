<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue;

use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Illuminate\Support\Str;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Queue\Exception;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;

class RabbitMQQueue extends Queue implements QueueContract
{
    protected $queueName;
    protected $queueOptions;
    protected $exchangeOptions;

    protected $declaredExchanges = [];
    protected $declaredQueues = [];

    /**
     * @var AmqpContext
     */
    protected $context;
    protected $config;
    protected $correlationId;

    public function __construct(AmqpContext $context, array $config)
    {
        $this->config = $config;
        $this->context = $context;

        $this->queueName = $config['queue'] ?? $config['options']['queue']['name'];
        $this->queueOptions = $config['options']['queue'];
        $this->queueOptions['arguments'] = isset($this->queueOptions['arguments']) ?
            json_decode($this->queueOptions['arguments'], true) : [];

        $this->exchangeOptions = $config['options']['exchange'];
        $this->exchangeOptions['arguments'] = isset($this->exchangeOptions['arguments']) ?
            json_decode($this->exchangeOptions['arguments'], true) : [];
    }

    /**
     * {@inheritdoc}
     */
    public function size($queueName = null): int
    {
        /** @var AmqpQueue $queue */
        [$queue] = $this->declareEverything($queueName);

        return $this->context->declareQueue($queue);
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $queue, $data), $queue, []);
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function pushRaw($payload, $queueName = null, array $options = [])
    {
        /**
         * @var AmqpTopic
         * @var AmqpQueue $queue
         */
        [$queue, $topic] = $this->declareEverything($queueName);

        /** @var AmqpMessage $message */
        $message = $this->context->createMessage($payload);

        $message->setCorrelationId($this->getCorrelationId());
        $message->setContentType('application/json');
        $message->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);

        if (isset($options['routing_key'])) {
            $message->setRoutingKey($options['routing_key']);
        } else {
            $message->setRoutingKey($queue->getQueueName());
        }

        if (isset($options['priority'])) {
            $message->setPriority($options['priority']);
        }

        if (isset($options['expiration'])) {
            $message->setExpiration($options['expiration']);
        }

        if (isset($options['headers'])) {
            $message->setHeaders($options['headers']);
        }

        if (isset($options['properties'])) {
            $message->setProperties($options['properties']);
        }

        if (isset($options['attempts'])) {
            $message->setProperty(RabbitMQJob::ATTEMPT_COUNT_HEADERS_KEY, $options['attempts']);
        }

        $producer = $this->context->createProducer();
        if (isset($options['delay']) && $options['delay'] > 0) {
            $producer->setDeliveryDelay($options['delay'] * 1000);
        }

        $producer->send($topic, $message);

        return $message->getCorrelationId();
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $queue, $data), $queue, ['delay' => $this->secondsUntil($delay)]);
    }

    /**
     * {@inheritDoc}
     * @throws Exception
     */
    public function release($delay, $job, $data, $queue, $attempts = 0)
    {
        return $this->pushRaw($this->createPayload($job, $queue, $data), $queue, [
            'delay' => $this->secondsUntil($delay),
            'attempts' => $attempts,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function pop($queueName = null)
    {
        /** @var AmqpQueue $queue */
        [$queue] = $this->declareEverything($queueName);

        $consumer = $this->context->createConsumer($queue);

        if ($message = $consumer->receiveNoWait()) {
            return new RabbitMQJob($this->container, $this, $consumer, $message);
        }

        return null;
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
    public function setCorrelationId(string $id): void
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
    public function declareEverything(string $queueName = null): array
    {
        $queueName = $this->getQueueName($queueName);
        $exchangeName = $this->exchangeOptions['name'] ?: $queueName;

        $topic = $this->context->createTopic($exchangeName);
        $topic->setType($this->exchangeOptions['type']);
        $topic->setArguments($this->exchangeOptions['arguments']);
        if ($this->exchangeOptions['passive']) {
            $topic->addFlag(AmqpTopic::FLAG_PASSIVE);
        }
        if ($this->exchangeOptions['durable']) {
            $topic->addFlag(AmqpTopic::FLAG_DURABLE);
        }
        if ($this->exchangeOptions['auto_delete']) {
            $topic->addFlag(AmqpTopic::FLAG_AUTODELETE);
        }

        if ($this->exchangeOptions['declare'] && !in_array($exchangeName, $this->declaredExchanges, true)) {
            $this->context->declareTopic($topic);

            $this->declaredExchanges[] = $exchangeName;
        }

        $queue = $this->context->createQueue($queueName);
        $queue->setArguments($this->queueOptions['arguments']);
        if ($this->queueOptions['passive']) {
            $queue->addFlag(AmqpQueue::FLAG_PASSIVE);
        }
        if ($this->queueOptions['durable']) {
            $queue->addFlag(AmqpQueue::FLAG_DURABLE);
        }
        if ($this->queueOptions['exclusive']) {
            $queue->addFlag(AmqpQueue::FLAG_EXCLUSIVE);
        }
        if ($this->queueOptions['auto_delete']) {
            $queue->addFlag(AmqpQueue::FLAG_AUTODELETE);
        }

        if ($this->queueOptions['declare'] && !in_array($queueName, $this->declaredQueues, true)) {
            $this->context->declareQueue($queue);

            $this->declaredQueues[] = $queueName;
        }

        if ($this->queueOptions['bind']) {
            $this->context->bind(new AmqpBind($queue, $topic, $queue->getQueueName()));
        }

        return [$queue, $topic];
    }

    protected function getQueueName($queueName = null)
    {
        return $queueName ?: $this->queueName;
    }

    protected function createPayloadArray($job, $queue, $data = '')
    {
        return array_merge(parent::createPayloadArray($job, $queue, $data), [
            'id' => $this->getRandomId(),
        ]);
    }

    /**
     * Get a random ID string.
     *
     * @return string
     */
    protected function getRandomId(): string
    {
        return Str::random(32);
    }
}
