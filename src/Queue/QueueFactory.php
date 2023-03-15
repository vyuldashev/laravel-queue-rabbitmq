<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue;

use Illuminate\Support\Arr;
use VladimirYuldashev\LaravelQueueRabbitMQ\Horizon\RabbitMQQueue as HorizonRabbitMQQueue;
use VladimirYuldashev\LaravelQueueRabbitMQ\Octane\RabbitMQQueue as OctaneRabbitMQQueue;

class QueueFactory
{
    public static function make(array $config = []): RabbitMQQueue
    {
        $queueConfig = QueueConfigFactory::make($config);
        $worker = Arr::get($config, 'worker', 'default');

        if (strtolower($worker) == 'default') {
            return new RabbitMQQueue($queueConfig);
        }

        if (strtolower($worker) == 'horizon') {
            return new HorizonRabbitMQQueue($queueConfig);
        }

        if (strtolower($worker) == 'octane') {
            return new OctaneRabbitMQQueue($queueConfig);
        }

        return new $worker($queueConfig);
    }
}
