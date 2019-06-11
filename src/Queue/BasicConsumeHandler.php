<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue;

use Interop\Queue\Context;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpConsumer;
use Illuminate\Container\Container;
use Interop\Amqp\AmqpSubscriptionConsumer;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;

class BasicConsumeHandler
{
    /**
     * @var AmqpSubscriptionConsumer
     */
    private $subscriptionConsumer;

    /**
     * @var AmqpConsumer
     */
    private $consumer;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var AmqpQueue
     */
    private $queue;

    /**
     * @var RabbitMQJob
     */
    private $job;

    /**
     * @var array
     */
    private $options = [];

    /**
     * ConsumeHelper constructor.
     * @param Context $context
     * @param AmqpQueue $queue
     */
    public function __construct(Context $context, AmqpQueue $queue, array $options = [])
    {
        $this->context = $context;
        $this->queue = $queue;
        $this->options = $options;
    }

    /**
     * @param Container $container
     * @param RabbitMQQueue $rabbitMQQueue
     * @return RabbitMQJob|null
     * @throws \Interop\Queue\Exception\SubscriptionConsumerNotSupportedException
     */
    public function getJob(Container $container, RabbitMQQueue $rabbitMQQueue): ?RabbitMQJob
    {
        $this->job = null;

        if (! $this->subscriptionConsumer) {
            $this->consumer = $this->context->createConsumer($this->queue);
            $this->subscriptionConsumer = $this->context->createSubscriptionConsumer();

            $this->subscriptionConsumer
                ->subscribe($this->consumer, function ($message) use ($container, $rabbitMQQueue) {
                    $this->job = new RabbitMQJob($container, $rabbitMQQueue, $this->consumer, $message);

                    return false;
                });
        }
        $this->subscriptionConsumer->consume($this->options['timeout'] ?? 10000);

        return $this->job;
    }
}
