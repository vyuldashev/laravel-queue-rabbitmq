<?php namespace FintechFab\LaravelQueueRabbitMQ\Queue\Connectors;

use AMQPConnection;
use FintechFab\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;
use Illuminate\Queue\Connectors\ConnectorInterface;

class RabbitMQConnector implements ConnectorInterface
{

	/**
	 * Establish a queue connection.
	 *
	 * @param  array $config
	 *
	 * @return \Illuminate\Queue\QueueInterface
	 */
	public function connect(array $config)
	{

		// create connection with AMQP
		$connection = new AMQPConnection($config);
		$connection->connect();

		return new RabbitMQQueue(
			$connection,
			$config['queue'],
			$config['exchange_name']
		);
	}
}