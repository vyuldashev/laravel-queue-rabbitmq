<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks;

use Interop\Amqp\AmqpConnectionFactory;
use Interop\Queue\Context;

class CustomContextAmqpConnectionFactoryMock implements AmqpConnectionFactory
{
    public static $context;

    public function createContext(): Context
    {
        return static::$context;
    }
}
