<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue;

use Illuminate\Support\Arr;
use VladimirYuldashev\LaravelQueueRabbitMQ\Horizon\RabbitMQQueue as HorizonRabbitMQQueue;

final class QueueFactory
{
    protected $queue;

    /**
     * Create a Queue
     */
    public function make(array $config = []): RabbitMQQueue
    {
        $queueConfig = $this->createQueueConfig($config);
        $worker = Arr::get($config, 'worker', 'default');

        if (strtolower($worker) == 'default') {
            return $this->queue = new RabbitMQQueue($queueConfig);
        }

        if (strtolower($worker) == 'horizon') {
            return $this->queue = new HorizonRabbitMQQueue($queueConfig);
        }

        return $this->queue = new $worker($queueConfig);
    }

    /**
     * Create a config object from config array
     */
    private function createQueueConfig(array $config = []): QueueConfig
    {
        return tap(new QueueConfig(), function (QueueConfig $queueConfig) use ($config) {
            if (! empty($queue = Arr::get($config, 'queue'))) {
                $queueConfig->setQueue($queue);
            }
            if (! empty($afterCommit = Arr::get($config, 'after_commit'))) {
                $queueConfig->setDispatchAfterCommit($afterCommit);
            }

            if (! empty($queueOptionsConfig = Arr::get($config, 'options.queue'))) {
                $queueConfig
                    ->setAbstractJob(Arr::pull($queueOptionsConfig, 'job'))
                    // Feature: Prioritize delayed messages.
                    ->setPrioritizeDelayed(Arr::pull($queueOptionsConfig, 'prioritize_delayed'))
                    ->setQueueMaxPriority(Arr::pull($queueOptionsConfig, 'queue_max_priority'))
                    // Feature: Working with Exchange and routing-keys
                    ->setExchange(Arr::pull($queueOptionsConfig, 'exchange'))
                    ->setExchangeType(Arr::pull($queueOptionsConfig, 'exchange_type'))
                    ->setExchangeRoutingKey(Arr::pull($queueOptionsConfig, 'exchange_routing_key'))
                    // Feature: Reroute failed messages
                    ->setRerouteFailed(Arr::pull($queueOptionsConfig, 'reroute_failed'))
                    ->setFailedExchange(Arr::pull($queueOptionsConfig, 'failed_exchange'))
                    ->setFailedRoutingKey(Arr::pull($queueOptionsConfig, 'failed_routing_key'))
                    // Feature: Mark queue as quorum
                    ->setQuorum(Arr::pull($queueOptionsConfig, 'quorum'))
                    // All extra options not defined
                    ->setOptions($queueOptionsConfig);
            }
        });
    }
}
