<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Arr;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;
use VladimirYuldashev\LaravelQueueRabbitMQ\Horizon\RabbitMQQueue as HorizonRabbitMQQueue;

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
        RabbitMQQueue $rabbitmq,
        AMQPMessage $message,
        string $queue
    ) {
        $this->rabbitmq = $rabbitmq;
        $this->message = $message;
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

        if (!$headers) {
            return 0;
        }

        $data = $headers->getNativeData();

        $laravelAttempts = Arr::get($data, 'laravel.attempts', 0);
        $xDeathCount = Arr::get($headers->getNativeData(), 'x-death.0.count', 0);

        return $laravelAttempts + $xDeathCount;
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException
     */
    public function delete(): void
    {
        parent::delete();

        $this->rabbitmq->ack($this);

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
        parent::release($delay);

        if ($delay > 0) {
            $this->rabbitmq->ack($this);

            $this->rabbitmq->laterRaw($delay, $this->message->body, $this->queue, $this->attempts());

            return;
        }

        $this->rabbitmq->reject($this);
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
