<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs;

use Illuminate\Container\Container;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;
use PhpAmqpLib\Message\AMQPMessage;

interface RabbitMQJobFactoryInterface
{
    public function create(
        Container $container,
        RabbitMQQueue $rabbitmq,
        AMQPMessage $message,
        string $connectionName,
        string $queue
    );
}
