<?php namespace FintechFab\LaravelQueueRabbitMQ;

use FintechFab\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;
use Illuminate\Support\ServiceProvider;
use Queue;

class LaravelQueueRabbitMQServiceProvider extends ServiceProvider
{

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->booted(function () {

			Queue::extend('rabbitmq', function () {
				return new RabbitMQConnector;
			});

		});
	}
}