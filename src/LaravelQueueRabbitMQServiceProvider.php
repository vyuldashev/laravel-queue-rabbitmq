<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ;

use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use VladimirYuldashev\LaravelQueueRabbitMQ\Console;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

class LaravelQueueRabbitMQServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/rabbitmq.php',
            'queue.connections.rabbitmq'
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ConsumeCommand::class,
                Console\ExchangeMakeCommand::class,
                Console\QueueBindCommand::class,
                Console\QueueMakeCommand::class,
                Console\QueuePurgeCommand::class,
            ]);
        }
    }

    /**
     * Register the application's event listeners.
     *
     * @return void
     */
    public function boot(): void
    {
        /** @var QueueManager $queue */
        $queue = $this->app['queue'];

        $queue->addConnector('rabbitmq', function () {
            return new RabbitMQConnector($this->app['events']);
        });
    }
}
