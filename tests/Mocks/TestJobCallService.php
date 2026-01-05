<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class TestJobCallService implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $service;

    public $method;

    public function __construct(string $service, string $method)
    {
        $this->service = $service;
        $this->method = $method;
    }

    public function handle(): void
    {
        $service = app($this->service);
        if (method_exists($service, $this->method)) {
            $service->{$this->method}();
        } else {
            $callback = $service->{$this->method};
            $callback();
        }
    }
}
