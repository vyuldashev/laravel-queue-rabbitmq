<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class TestEncryptedJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, Queueable;

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
