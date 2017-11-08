<?php
namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mock;

class CustomContextAmqpConnectionFactoryMock implements \Interop\Amqp\AmqpConnectionFactory
{
    public static $context;

    public function createContext()
    {
        return static::$context;
    }
}
