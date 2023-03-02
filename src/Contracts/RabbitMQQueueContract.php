<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Contracts;

use PhpAmqpLib\Connection\AbstractConnection;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\QueueConfig;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

interface RabbitMQQueueContract
{
    public function __construct(QueueConfig $config);

    public function setConnection(AbstractConnection $connection): RabbitMQQueue;
}
