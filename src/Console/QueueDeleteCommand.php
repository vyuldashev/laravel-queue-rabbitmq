<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Console;

use Exception;
use Illuminate\Console\Command;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

class QueueDeleteCommand extends Command
{
    protected $signature = 'rabbitmq:queue-delete
                           {name : The name of the queue to delete}
                           {connection=rabbitmq : The name of the queue connection to use}
                           {--unused=0 : Check if queue has no consumers}
                           {--empty=0 : Check if queue is empty}';

    protected $description = 'Delete queue';

    /**
     * @throws Exception
     */
    public function handle(RabbitMQConnector $connector): void
    {
        $config = $this->laravel['config']->get('queue.connections.'.$this->argument('connection'));

        $queue = $connector->connect($config);

        if (! $queue->isQueueExists($this->argument('name'))) {
            $this->warn('Queue does not exist.');

            return;
        }

        $queue->deleteQueue(
            $this->argument('name'),
            (bool) $this->option('unused'),
            (bool) $this->option('empty')
        );

        $this->info('Queue deleted successfully.');
    }
}
