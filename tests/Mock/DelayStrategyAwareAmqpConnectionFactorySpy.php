<?php
namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mock;

use Enqueue\AmqpTools\DelayStrategy;
use Enqueue\AmqpTools\DelayStrategyAware;

class DelayStrategyAwareAmqpConnectionFactorySpy implements \Interop\Amqp\AmqpConnectionFactory, DelayStrategyAware
{
    /** @var \Closure */
    public static $spy;

    public function createContext()
    {
        return new AmqpContextMock();
    }

    /**
     * @param DelayStrategy $delayStrategy
     *
     * @return self
     */
    public function setDelayStrategy(DelayStrategy $delayStrategy = null)
    {
        $spy = static::$spy;

        $spy($delayStrategy);
    }
}
