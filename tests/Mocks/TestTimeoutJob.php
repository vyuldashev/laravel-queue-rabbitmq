<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class TestTimeoutJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $timeout = 1;

    public function handle(): void
    {
        sleep(3);
    }
}
