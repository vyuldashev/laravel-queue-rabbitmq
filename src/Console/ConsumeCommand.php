<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Console;

use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

class ConsumeCommand extends Command
{
    protected $signature = 'rabbitmq:consume
                           {queue}
                           {connection=rabbitmq : The name of the queue connection to work}
                           {--consumer-tag}
                           {--no-local}
                           {--no-ack}
                           {--exclusive}';
    protected $description = '';

    public function handle(RabbitMQConnector $connector): void
    {
        $config = $this->laravel['config']->get('queue.connections.' . $this->argument('connection'));

        $queue = $connector->connect($config);

        $channel = $queue->getChannel();

        $channel->basic_consume(
            $this->argument('queue'),
            $this->consumerTag(),
            $this->option('no-local'),
            $this->option('no-ack'),
            $this->option('exclusive'),
            false,
            [$this, 'processMessage']
        );

        while($channel->is_consuming()) {
            $channel->wait();
        }
    }

    protected function processMessage(AMQPMessage $AMQPMessage): void
    {

    }

    protected function consumerTag(): string
    {
        if ($this->option('consumer-tag')) {
            return (string)$this->option('consumer-tag');
        }

        return config('app.name') . '_' . getmygid();
    }
}
