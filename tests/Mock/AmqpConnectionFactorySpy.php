<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mock;

use Interop\Queue\Context;

class AmqpConnectionFactorySpy implements \Interop\Amqp\AmqpConnectionFactory
{
    /** @var \Closure */
    public static $spy;

    public function __construct($config)
    {
        $spy = static::$spy;

        $spy($config);
    }

    public function createContext(): Context
    {
        return new AmqpContextMock();
    }
}
