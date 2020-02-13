<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Arr;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Horizon\RabbitMQQueue as HorizonRabbitMQQueue;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQJob extends Job implements JobContract
{
    /**
     * The RabbitMQ queue instance.
     *
     * @var RabbitMQQueue
     */
    protected $rabbitmq;

    /**
     * The RabbitMQ message instance.
     *
     * @var AMQPMessage
     */
    protected $message;

    /**
     * The JSON decoded version of "$message".
     *
     * @var array
     */
    protected $decoded;

    public function __construct(
        Container $container,
        RabbitMQQueue $rabbitmq,
        AMQPMessage $message,
        string $connectionName,
        string $queue
    ) {
        $this->container = $container;
        $this->rabbitmq = $rabbitmq;
        $this->message = $message;
        $this->connectionName = $connectionName;
        $this->queue = $queue;
        $this->decoded = $this->payload();
    }

    /**
     * {@inheritdoc}
     */
    public function getJobId()
    {
        return json_decode($this->message->getBody(), true)['id'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getRawBody(): string
    {
        return $this->message->getBody();
    }

    /**
     * {@inheritdoc}
     */
    public function attempts(): int
    {
        /** @var AMQPTable|null $headers */
        $headers = Arr::get($this->message->get_properties(), 'application_headers');

        if (! $headers) {
            return 1;
        }

        $data = $headers->getNativeData();

        $laravelAttempts = (int) Arr::get($data, 'laravel.attempts', 0);

        return ($laravelAttempts) + 1;
    }

    public function fail($e = null): void
    {
        parent::fail($e);

        // We must tel rabbitMQ this Job is failed
        // The message must be rejected when the Job marked as failed, in case rabbitMQ wants to do some extra magic.
        // like: Death lettering the message to an other exchange/routing-key.
        $this->rabbitmq->reject($this);
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException
     */
    public function delete(): void
    {
        parent::delete();

        // When delete is called and the Job was not failed, the message must be acknowledged.
        // This is because this is a controlled call by a developer. So the message was handled correct.
        if (! $this->failed) {
            $this->rabbitmq->ack($this);
        }

        // required for Laravel Horizon
        if ($this->rabbitmq instanceof HorizonRabbitMQQueue) {
            $this->rabbitmq->deleteReserved($this->queue, $this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function release($delay = 0): void
    {
        parent::release();

        // Always create a new message when this Job is released
        $this->rabbitmq->laterRaw($delay, $this->message->body, $this->queue, $this->attempts());

        // Releasing a Job means the message was failed to process.
        // Because this Job is always recreated and pushed as new message, this Job is correctly handled.
        // We must tell rabbitMQ this fact.
        $this->rabbitmq->ack($this);
    }

    /**
     * Get the underlying RabbitMQ connection.
     *
     * @return RabbitMQQueue
     */
    public function getRabbitMQ(): RabbitMQQueue
    {
        return $this->rabbitmq;
    }

    /**
     * Get the underlying RabbitMQ message.
     *
     * @return AMQPMessage
     */
    public function getRabbitMQMessage(): AMQPMessage
    {
        return $this->message;
    }
}
