<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Console;

use Exception;
use Illuminate\Console\Command;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

class QueueDeclareCommand extends Command
{
    protected $signature = 'rabbitmq:queue-declare
                           {name : The name of the queue to declare}
                           {connection=rabbitmq : The name of the queue connection to use}
                           {--durable=1}
                           {--auto-delete=0}';

    protected $description = 'Declare queue';

    /**
     * @param RabbitMQConnector $connector
     * @throws Exception
     */
    public function handle(RabbitMQConnector $connector): void
    {
        $config = $this->laravel['config']->get('queue.connections.'.$this->argument('connection'));

        $queue = $connector->connect($config);

        if ($queue->isQueueExists($this->argument('name'))) {
            $this->warn('Queue already exists.');

            return;
        }

        $queue->declareQueue(
            $this->argument('name'),
            (bool) $this->option('durable'),
            (bool) $this->option('auto-delete')
        );

        $this->info('Queue declared successfully.');
    }
}
