<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks;

use Illuminate\Contracts\Queue\ShouldQueue;

class TestJob implements ShouldQueue
{
    public function handle(): void
    {
        //
    }
}
