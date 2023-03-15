<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Octane;

use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue as BaseRabbitMQQueue;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\ReconnectTrait;

class RabbitMQQueue extends BaseRabbitMQQueue
{
    use ReconnectTrait;
}
