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
			/**
			 * @var \Illuminate\Queue\QueueManager $manager
			 */
			$manager = $this->app['queue'];
			$manager->addConnector('rabbitmq', function () {
				return new RabbitMQConnector;
			});
		});
	}

	/**
	 * Boot the service provider ($this->app->booted() is not called in Lumen, see Application::booted()).
	 *
	 * @return void
	 */

	public function boot()
	{
		$manager = $this->app['queue'];
		$manager->addConnector('rabbitmq', function () {
			return new RabbitMQConnector;
		});
	}

}
