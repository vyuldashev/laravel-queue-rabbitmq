<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Console;

use Exception;
use Illuminate\Console\Command;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

class QueuePurgeCommand extends Command
{
    protected $signature = 'rabbitmq:queue-purge
                           {queue}
                           {connection=rabbitmq : The name of the queue connection to use}';

    protected $description = 'Purge all messages in queue';

    /**
     * @param RabbitMQConnector $connector
     * @throws Exception
     */
    public function handle(RabbitMQConnector $connector): void
    {
        $config = $this->laravel['config']->get('queue.connections.'.$this->argument('connection'));

        $queue = $connector->connect($config);

        $queue->purge($this->argument('queue'));

        $this->info('Queue purged successfully.');
    }
}
