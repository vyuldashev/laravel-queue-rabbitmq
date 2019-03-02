<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mock;

use Enqueue\AmqpTools\DelayStrategy;
use Enqueue\AmqpTools\DelayStrategyAware;
use Interop\Queue\Context;

class DelayStrategyAwareAmqpConnectionFactorySpy implements \Interop\Amqp\AmqpConnectionFactory, DelayStrategyAware
{
    /** @var \Closure */
    public static $spy;

    public function createContext(): Context
    {
        return new AmqpContextMock();
    }

    /**
     * @param DelayStrategy $delayStrategy
     *
     * @return self
     */
    public function setDelayStrategy(DelayStrategy $delayStrategy = null): DelayStrategyAware
    {
        $spy = static::$spy;

        $spy($delayStrategy);

        return $this;
    }
}
