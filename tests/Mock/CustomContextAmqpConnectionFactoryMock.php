<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mock;

use Interop\Queue\Context;

class CustomContextAmqpConnectionFactoryMock implements \Interop\Amqp\AmqpConnectionFactory
{
    public static $context;

    public function createContext(): Context
    {
        return static::$context;
    }
}
