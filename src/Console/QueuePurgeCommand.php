<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Console;

use Illuminate\Console\Command;

class QueuePurgeCommand extends Command
{
    protected $signature = 'rabbitmq:queue-purge';
    protected $description = '';
}
