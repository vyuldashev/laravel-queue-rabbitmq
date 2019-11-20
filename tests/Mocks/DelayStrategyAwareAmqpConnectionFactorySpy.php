<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks;

use Closure;
use Enqueue\AmqpTools\DelayStrategy;
use Enqueue\AmqpTools\DelayStrategyAware;
use Interop\Amqp\AmqpConnectionFactory;
use Interop\Queue\Context;

class DelayStrategyAwareAmqpConnectionFactorySpy implements AmqpConnectionFactory, DelayStrategyAware
{
    /** @var Closure */
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
