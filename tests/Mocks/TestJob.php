<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests;

use Illuminate\Contracts\Queue\ShouldQueue;

class TestJob implements ShouldQueue
{
    public function handle(): void
    {
    }
}
