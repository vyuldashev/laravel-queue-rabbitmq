<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Console;

use Exception;
use Illuminate\Console\Command;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

class ExchangeDeclareCommand extends Command
{
    protected $signature = 'rabbitmq:exchange-declare
                            {name : The name of the exchange to declare}
                            {connection=rabbitmq : The name of the queue connection to use}
                            {--type=direct}
                            {--durable}
                            {--auto-delete}';

    protected $description = 'Declare exchange';

    /**
     * @param RabbitMQConnector $connector
     * @throws Exception
     */
    public function handle(RabbitMQConnector $connector): void
    {
        $config = $this->laravel['config']->get('queue.connections.'.$this->argument('connection'));

        $queue = $connector->connect($config);
        $channel = $queue->getChannel();

        try {
            $channel->exchange_declare($this->argument('name'), '', true);
        } catch (AMQPProtocolChannelException $exception) {
            if ($exception->amqp_reply_code === 404) {
                $this->declareExchange($queue->getConnection()->channel());

                $this->info('Exchange declared successfully.');

                return;
            }

            throw $exception;
        }

        $this->warn('Exchange already exists.');
    }

    protected function declareExchange(AMQPChannel $channel): void
    {
        $channel->exchange_declare(
            $this->argument('name'),
            $this->option('type'),
            false,
            $this->option('durable'),
            $this->option('auto-delete'),
            false,
            true
        );
    }
}
