<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools;

interface PrioritizeAware
{
    public function setPrioritize(?bool $prioritize = null);
}
