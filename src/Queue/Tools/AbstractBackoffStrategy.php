<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools;

use Symfony\Component\HttpFoundation\ParameterBag;

abstract class AbstractBackoffStrategy implements BackoffStrategy
{
    protected $options;

    public function __construct(array $options = [])
    {
        $this->options = new ParameterBag($options);
    }
}
