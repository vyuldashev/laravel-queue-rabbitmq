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
        /**
         * @var \Illuminate\Queue\QueueManager
         */
        $manager = $this->app['queue'];
        $manager->addConnector('rabbitmq', function () {
            return new RabbitMQConnector;
        });
    }
}
