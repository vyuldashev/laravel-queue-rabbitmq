<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks;

use Illuminate\Contracts\Queue\ShouldQueue;

class TestJob implements ShouldQueue
{
    public $i;

    public function __construct($i = 0)
    {
        $this->i = $i;
    }

    public function handle(): void
    {
        //
    }
}
