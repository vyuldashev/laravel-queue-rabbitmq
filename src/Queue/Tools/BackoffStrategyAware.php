<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools;

interface BackoffStrategyAware
{
    public function setBackoffStrategy(BackoffStrategy $backoffStrategy = null);
}
