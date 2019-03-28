<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools;

trait BackoffStrategyAwareTrait
{
    /**
     * @var BackoffStrategy
     */
    protected $backoffStrategy;

    /**
     * @param BackoffStrategy|null $backoffStrategy
     * @return BackoffStrategyAwareTrait
     */
    public function setBackoffStrategy(BackoffStrategy $backoffStrategy = null)
    {
        $this->backoffStrategy = $backoffStrategy;

        return $this;
    }
}
