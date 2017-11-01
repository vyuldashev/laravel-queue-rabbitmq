<?php
namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mock;

class AmqpConnectionFactorySpy implements \Interop\Amqp\AmqpConnectionFactory
{
    /** @var \Closure */
    public static $spy;

    public function __construct($config)
    {
        $spy = static::$spy;

        $spy($config);
    }

    public function createContext()
    {
        return new AmqpContextMock();
    }
}
