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

		if (!isset($config['exchange_type'])) {
			$config['exchange_type'] = AMQP_EX_TYPE_DIRECT;
		}

		if (!isset($config['exchange_flags'])) {
			$config['exchange_flags'] = AMQP_DURABLE;
		}

		return new RabbitMQQueue(
			$connection,
			$config['queue'],
			$config['exchange_name'],
			$config['exchange_type'],
			$config['exchange_flags']
		);
	}
}