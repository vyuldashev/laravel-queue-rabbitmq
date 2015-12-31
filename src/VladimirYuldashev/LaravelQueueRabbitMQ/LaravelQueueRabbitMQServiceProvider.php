<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ;

use Illuminate\Support\ServiceProvider;
use Queue;
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
		$this->app->booted(function () {
			/**
			 * @var \Illuminate\Queue\QueueManager $manager
			 */
			$manager = $this->app['queue'];
			$manager->addConnector('rabbitmq', function () {
				return new RabbitMQConnector;
			});
		});
	}

}