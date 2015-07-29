<?php namespace FintechFab\LaravelQueueRabbitMQ;

use FintechFab\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;
use Illuminate\Support\ServiceProvider;

class LaravelQueueRabbitMQServiceProvider extends ServiceProvider
{

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Register the application's event listeners.
     *
     * @return void
     */
    public function boot()
    {
        /**
         * @var \Illuminate\Queue\QueueManager $manager
         */
        $manager = $this->app['queue'];
        $manager->addConnector('rabbitmq', function () {
            return new RabbitMQConnector;
        });
    }

}