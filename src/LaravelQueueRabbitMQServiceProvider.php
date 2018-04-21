<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ;

use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

class LaravelQueueRabbitMQServiceProvider extends ServiceProvider
{
    /**
     * Register the application's event listeners.
     *
     * @return void
     */
    public function boot()
    {
        $path = realpath(__DIR__.'/../config/rabbitmq.php');
        $this->publishes([$path => config_path('rabbitmq.php')], 'config');
        $this->mergeConfigFrom($path, 'queue.connections.rabbitmq');
        
        /** @var QueueManager $queue */
        $queue = $this->app['queue'];
        
        $queue->addConnector('rabbitmq', function () {
            return new RabbitMQConnector($this->app['events']);
        });
    }
}
