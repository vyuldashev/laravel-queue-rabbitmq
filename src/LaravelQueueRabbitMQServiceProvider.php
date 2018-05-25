<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ;

use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

class LaravelQueueRabbitMQServiceProvider extends ServiceProvider
{
    /**
     * Add rabbitmq configuration and register connector.
     *
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__ . '/../config/rabbitmq.php';

        $this->mergeConfigFrom($configPath, 'queue.connections.rabbitmq');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                $configPath => $this->app->configPath('rabbitmq.php')
            ], 'config');
        }

        $this->addRabbitMQConnector();
    }


    /**
     * Add rabbitMQ connector when queue manager is resolved.
     */
    private function addRabbitMQConnector()
    {
        $callback = function (QueueManager $queueManager) {
            $queueManager->addConnector('rabbitmq', function () {
                return new RabbitMQConnector($this->app['events']);
            });
        };

        $this->app->resolved('queue')
            ? $callback($this->app['queue'])
            : $this->app->afterResolving('queue', $callback);
    }
}
