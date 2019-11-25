<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Console;

use Exception;
use Illuminate\Console\Command;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

class QueueMakeCommand extends Command
{
    protected $signature = 'rabbitmq:make-queue
                           {name : The name of the queue to declare}
                           {connection=rabbitmq : The name of the queue connection to work}
                           {--durable}
                           {--auto-delete}';

    protected $description = '';

    /**
     * @param RabbitMQConnector $connector
     * @throws Exception
     */
    public function handle(RabbitMQConnector $connector): void
    {
        $config = $this->laravel['config']->get('queue.connections.' . $this->argument('connection'));

        $queue = $connector->connect($config);

        $channel = $queue->getChannel();

        try {
            $channel->queue_declare($this->argument('name'), true);
        } catch (AMQPProtocolChannelException $exception) {
            if ($exception->amqp_reply_code === 404) {
                $this->declareQueue($queue->getConnection()->channel());

                $this->info('Queue declared successfully.');

                return;
            }

            throw $exception;
        }

        $this->warn('Queue already exists.');
    }

    protected function declareQueue(AMQPChannel $channel): void
    {
        $channel->queue_declare(
            $this->argument('name'),
            false,
            $this->option('durable'),
            false,
            $this->option('auto-delete'),
            false
        );
    }
}
