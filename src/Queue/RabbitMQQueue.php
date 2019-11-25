<?php

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
     * List of already declared queues.
     *
     * @var array
     */
    protected $queues;

    public function __construct(
        AbstractConnection $connection,
        AMQPChannel $channel,
        string $default
    ) {
        $this->connection = $connection;
        $this->channel = $channel;
        $this->default = $default;
    }

    /**
     * {@inheritdoc}
     */
    public function size($queue = null): int
    {
        // TODO count delayed too
        $queue = $this->getQueue($queue);

        [, $size] = $this->channel->queue_declare(
            $queue,
            false,
            true,
            false,
            false,
            false,
            new AMQPTable([
                'x-dead-letter-exchange' => $queue,
                'x-dead-letter-routing-key' => $queue,
            ])
        );

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

        if (!isset($this->queues[$queue])) {
            $this->channel->exchange_declare(
                $queue,
                AMQPExchangeType::DIRECT,
                false,
                true,
                true
            );

            $this->channel->queue_declare(
                $queue,
                false,
                true,
                false,
                false,
                false,
                new AMQPTable([
                    'x-dead-letter-exchange' => $queue,
                    'x-dead-letter-routing-key' => $queue,
                ])
            );

            $this->channel->queue_bind($queue, $queue, $queue);
        }

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

        $destinationQueue = $this->getQueue($queue);
        $delayedQueue = $this->getQueue($queue).'.delay.'.$ttl;

        $this->channel->exchange_declare(
            $destinationQueue,
            AMQPExchangeType::DIRECT,
            false,
            true,
            true
        );

        $this->channel->queue_declare(
            $delayedQueue,
            false,
            true,
            false,
            false,
            false,
            new AMQPTable([
                'x-dead-letter-exchange' => $destinationQueue,
                'x-dead-letter-routing-key' => $destinationQueue,
                'x-message-ttl' => $ttl, // TODO
            ])
        );

        $this->channel->queue_bind($destinationQueue, $destinationQueue, $destinationQueue);

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

            if (!isset($this->queues[$queue])) {
                $this->channel->exchange_declare(
                    $queue,
                    AMQPExchangeType::DIRECT,
                    false,
                    true,
                    true
                );

                $this->channel->queue_declare(
                    $queue,
                    false,
                    true,
                    false,
                    false,
                    false,
                    new AMQPTable([
                        'x-dead-letter-exchange' => $queue,
                        'x-dead-letter-routing-key' => $queue,
                    ])
                );

                $this->channel->queue_bind($queue, $queue, $queue);
            }

            $this->channel->batch_basic_publish($message);
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

    public function purge($queue = null): void
    {
        $this->channel->queue_purge($this->getQueue($queue));
        $this->channel->wait_for_pending_acks();
    }

    public function ack(RabbitMQJob $job): void
    {
        $this->channel->basic_ack($job->getRabbitMQMessage()->getDeliveryTag());
        $this->channel->wait_for_pending_acks();
    }

    public function reject(RabbitMQJob $job): void
    {
        $this->channel->basic_reject($job->getRabbitMQMessage()->getDeliveryTag(), false);
        $this->channel->wait_for_pending_acks();
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
