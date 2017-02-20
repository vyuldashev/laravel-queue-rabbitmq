<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ;

use Illuminate\Support\ServiceProvider;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

class LaravelQueueRabbitMQServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/rabbitmq.php', 'queue.connections.rabbitmq'
        );
    }

    /**
     * Register the application's event listeners.
     *
     * @return void
     */
    public function boot()
    {

        $appQueue = app('queue');
        $RabbitMQConnector = new RabbitMQConnector();

        $appQueue->stopping(function () use ($RabbitMQConnector) {
            $RabbitMQConnector->getConnection()->close();
        });

        $appQueue->addConnector('rabbitmq', function () use ($RabbitMQConnector) {
            return $RabbitMQConnector;
        });
    }
}
