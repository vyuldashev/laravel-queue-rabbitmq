<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue;

use Exception;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Illuminate\Support\Str;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;

class RabbitMQQueue extends Queue implements QueueContract
{
    /**
     * The RabbitMQ connection instance.
     *
     * @var AMQPChannel
     */
    protected $connection;

    /**
     * The RabbitMQ channel instance.
     *
     * @var AMQPChannel
     */
    protected $channel;

    /**
     * The name of the default queue.
     *
     * @var string
     */
    protected $default;

    /**
     * List of already declared exchanges.
     *
     * @var array
     */
    protected $exchanges = [];

    /**
     * List of already declared queues.
     *
     * @var array
     */
    protected $queues = [];

    /**
     * List of already bound queues to exchanges.
     *
     * @var array
     */
    protected $boundQueues = [];

    public function __construct(
        AbstractConnection $connection,
        string $default
    ) {
        $this->connection = $connection;
        $this->channel = $connection->channel();
        $this->default = $default;
    }

    /**
     * {@inheritdoc}
     *
     * @throws AMQPProtocolChannelException
     */
    public function size($queue = null): int
    {
        // TODO count delayed too
        $queue = $this->getQueue($queue);

        if (!$this->isQueueExists($queue)) {
            return 0;
        }

        // create a temporary channel, so the main channel will not be closed on exception
        $channel = $this->connection->channel();
        [, $size] = $channel->queue_declare($queue, true);
        $channel->close();

        return $size;
    }

    /**
     * {@inheritdoc}
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $queue, $data), $queue, []);
    }

    /**
     * {@inheritdoc}
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $queue = $this->getQueue($queue);

        $this->declareExchange($queue);
        $this->declareQueue($queue, true, false, [
            'x-dead-letter-exchange' => $queue,
            'x-dead-letter-routing-key' => $queue,
        ]);
        $this->bindQueue($queue, $queue, $queue);

        [$message, $correlationId] = $this->createMessage($payload);

        $this->channel->basic_publish($message, $queue, $queue, true, false);
        $this->channel->wait_for_pending_acks();

        return $correlationId;
    }

    /**
     * {@inheritdoc}
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->laterRaw(
            $delay,
            $this->createPayload($job, $queue, $data),
            $queue
        );
    }

    public function laterRaw($delay, $payload, $queue = null, $attempts = 0)
    {
        $ttl = $this->secondsUntil($delay) * 1000;

        if($ttl < 0) {
            return $this->pushRaw($payload, $queue, []);
        }

        $destinationQueue = $this->getQueue($queue);
        $delayedQueue = $this->getQueue($queue).'.delay.'.$ttl;

        $this->declareExchange($destinationQueue);
        $this->declareQueue($destinationQueue, true, false, [
            'x-dead-letter-exchange' => $destinationQueue,
            'x-dead-letter-routing-key' => $destinationQueue,
        ]);
        $this->declareQueue($delayedQueue, true, false, [
            'x-dead-letter-exchange' => $destinationQueue,
            'x-dead-letter-routing-key' => $destinationQueue,
            'x-message-ttl' => $ttl, // TODO
        ]);
        $this->bindQueue($destinationQueue, $destinationQueue, $destinationQueue);

        [$message, $correlationId] = $this->createMessage($payload, $attempts);

        $this->channel->basic_publish($message, null, $delayedQueue, true, false);
        $this->channel->wait_for_pending_acks();

        return $correlationId;
    }

    /**
     * {@inheritdoc}
     */
    public function bulk($jobs, $data = '', $queue = null): void
    {
        $queue = $this->getQueue($queue);

        foreach ((array)$jobs as $job) {
            [$message] = $this->createMessage(
                $this->createPayload($job, $queue, $data)
            );

            $this->declareExchange($queue);
            $this->declareQueue($queue, true, false, [
                'x-dead-letter-exchange' => $queue,
                'x-dead-letter-routing-key' => $queue,
            ]);
            $this->bindQueue($queue, $queue, $queue);

            $this->channel->batch_basic_publish($message, $queue, $queue);
        }

        $this->channel->publish_batch();
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function pop($queue = null)
    {
        try {
            $queue = $this->getQueue($queue);

            /** @var AMQPMessage|null $message */
            if ($message = $this->channel->basic_get($queue)) {
                return new RabbitMQJob($this, $message, $queue);
            }
        } catch (AMQPProtocolChannelException $exception) {
            // if there is not exchange or queue AMQP will throw exception with code 404
            // we need to catch it and return null
            if ($exception->amqp_reply_code === 404) {
                return null;
            }

            throw $exception;
        }

        return null;
    }

    public function getConnection(): AbstractConnection
    {
        return $this->connection;
    }

    public function getChannel(): AMQPChannel
    {
        return $this->channel;
    }

    public function getQueue($queue = null)
    {
        return $queue ?: $this->default;
    }

    /**
     * @param string $exchange
     * @return bool
     * @throws AMQPProtocolChannelException
     */
    public function isExchangeExists(string $exchange): bool
    {
        try {
            // create a temporary channel, so the main channel will not be closed on exception
            $channel = $this->connection->channel();
            $channel->exchange_declare($exchange, '', true);
            $channel->close();

            return true;
        } catch (AMQPProtocolChannelException $exception) {
            if ($exception->amqp_reply_code === 404) {
                return false;
            }

            throw $exception;
        }
    }

    public function declareExchange(
        string $name,
        string $type = AMQPExchangeType::DIRECT,
        bool $durable = true,
        bool $autoDelete = false
    ): void {
        if (in_array($name, $this->exchanges, true)) {
            return;
        }

        $this->channel->exchange_declare(
            $name,
            $type,
            false,
            $durable,
            $autoDelete,
            false,
            true
        );
    }

    /**
     * @param string $name
     * @return bool
     * @throws AMQPProtocolChannelException
     */
    public function isQueueExists(?string $name = null): bool
    {
        try {
            $name = $this->getQueue($name);

            // create a temporary channel, so the main channel will not be closed on exception
            $channel = $this->connection->channel();
            $channel->queue_declare($name, true);
            $channel->close();

            return true;
        } catch (AMQPProtocolChannelException $exception) {
            if ($exception->amqp_reply_code === 404) {
                return false;
            }

            throw $exception;
        }
    }

    public function declareQueue(string $name, bool $durable = true, bool $autoDelete = false, array $arguments = []): void
    {
        if (in_array($name, $this->queues, true)) {
            return;
        }

        $this->channel->queue_declare(
            $name,
            false,
            $durable,
            false,
            $autoDelete,
            false,
            new AMQPTable($arguments)
        );
    }

    public function bindQueue(string $queue, string $exchange, string $routingKey = ''): void
    {
        if (in_array(
            implode('', compact('queue', 'exchange', 'routingKey')),
            $this->boundQueues,
            true
        )) {
            return;
        }

        $this->channel->queue_bind($queue, $exchange, $routingKey);
    }

    public function purge($queue = null): void
    {
        // create a temporary channel, so the main channel will not be closed on exception
        $channel = $this->connection->channel();
        $channel->queue_purge($this->getQueue($queue));
        $channel->close();
    }

    public function ack(RabbitMQJob $job): void
    {
        $this->channel->basic_ack($job->getRabbitMQMessage()->getDeliveryTag());
        $this->channel->wait_for_pending_acks();
    }

    public function reject(RabbitMQJob $job, bool $requeue = false): void
    {
        $this->channel->basic_reject($job->getRabbitMQMessage()->getDeliveryTag(), $requeue);
        $this->channel->wait_for_pending_acks();
    }

    /**
     * @throws Exception
     */
    public function close(): void
    {
        $this->connection->close();
    }

    protected function createMessage($payload, int $attempts = 0): array
    {
        $properties = [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ];

        // todo
        if ($correlationId = json_decode($payload, true)['id'] ?? null) {
            $properties['correlation_id'] = $correlationId;
        }

        $message = new AMQPMessage($payload, $properties);

        $message->set('application_headers', new AMQPTable([
            'laravel' => [
                'attempts' => $attempts,
            ],
        ]));

        return [
            $message,
            $correlationId,
        ];
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
