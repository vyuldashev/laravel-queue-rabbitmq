<?php namespace FintechFab\LaravelQueueRabbitMQ\Queue;

use DateTime;
use FintechFab\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQQueue extends Queue implements QueueContract
{

	protected $connection;
	protected $channel;

	protected $defaultQueue;
	protected $exchange;
	protected $configQueue;
	protected $configExchange;

	/**
	 * @param AMQPConnection $amqpConnection
	 * @param array          $config
	 */
	public function __construct(AMQPStreamConnection $amqpConnection, $config)
	{
		$this->connection = $amqpConnection;
		$this->defaultQueue = $config['queue'];
		$this->exchange = isset($config['exchange_name']) ? $config['exchange_name'] : $this->getQueueName($config['queue']);
		$this->configQueue = $config['queue_params'];
		$this->configExchange = $config['exchange_params'];

		$this->channel = $this->getChannel();
	}

	/**
	 * Push a new job onto the queue.
	 *
	 * @param  string $job
	 * @param  mixed  $data
	 * @param  string $queue
	 *
	 * @return bool
	 */
	public function push($job, $data = '', $queue = null)
	{
		$queue = $this->getQueueName($queue);
		$payload = $this->createPayload($job, $data);
		$this->declareQueue($queue);

		// push job to a queue
		$message = new AMQPMessage($payload, [
			'Content-Type'  => 'application/json',
			'delivery_mode' => 2,
		]);

		//Set the routing key if there is one
		$routingKey = isset($this->configQueue['routing_key']) ? $this->configQueue['routing_key'] : '';

		$this->channel->basic_publish($message, $this->exchange, $routingKey);

		return true;
	}

	/**
	 * Push a raw payload onto the queue.
	 *
	 * @param  string $payload
	 * @param  string $queue
	 * @param  array  $options
	 *
	 * @return mixed
	 */
	public function pushRaw($payload, $queue = null, array $options = [])
	{
		$queue = $this->getQueueName($queue);
		$this->declareQueue($queue);

		// push job to a queue
		$message = new AMQPMessage($payload, [
			'Content-Type'  => 'application/json',
			'delivery_mode' => 2,
		]);

		//Set the routing key if there is one
		$routingKey = isset($this->configQueue['routing_key']) ? $this->configQueue['routing_key'] : '';

		$this->channel->basic_publish($message, $this->exchange, $routingKey);

		return true;
	}

	/**
	 * Push a new job onto the queue after a delay.
	 *
	 * @param  \DateTime|int $delay
	 * @param  string        $job
	 * @param  mixed         $data
	 * @param  string        $queue
	 *
	 * @return mixed
	 */
	public function later($delay, $job, $data = '', $queue = null)
	{
		$payload = $this->createPayload($job, $data);
		$this->declareQueue($queue);
		$exchange = $this->declareDelayedQueue($queue, $delay);

		// push job to a queue
		$message = new AMQPMessage($payload, [
			'Content-Type'  => 'application/json',
			'delivery_mode' => 2,
		]);

		//Set the routing key if there is one
		$routingKey = isset($this->configQueue['routing_key']) ? $this->configQueue['routing_key'] : '';

		$this->channel->basic_publish($message, $exchange, $routingKey);

		return true;
	}

	/**
	 * Pop the next job off of the queue.
	 *
	 * @param string|null $queue
	 *
	 * @return \Illuminate\Queue\Jobs\Job|null
	 */
	public function pop($queue = null)
	{
		// declare queue if not exists
		$this->declareQueue($queue);

		// get envelope
		$message = $this->channel->basic_get($queue);

		if ($message instanceof AMQPMessage) {
			return new RabbitMQJob($this->container, $this->channel, $queue, $message);
		}

		return null;
	}

	/**
	 * @param string $queue
	 *
	 * @return string
	 */
	private function getQueueName($queue)
	{
		return $queue ?: $this->defaultQueue;
	}

	/**
	 * @return AMQPChannel
	 */
	private function getChannel()
	{
		return $this->connection->channel();
	}

	/**
	 * @param string $name
	 */
	private function declareQueue($name)
	{
		$name = $this->getQueueName($name);

		// declare queue
		$this->channel->queue_declare(
			$name,
			$this->configQueue['passive'],
			$this->configQueue['durable'],
			$this->configQueue['exclusive'],
			$this->configQueue['auto_delete']
		);

		// declare exchange
		$this->channel->exchange_declare(
			$this->exchange,
			$this->configExchange['type'],
			$this->configExchange['passive'],
			$this->configExchange['durable'],
			$this->configExchange['auto_delete']
		);

		//Get the routing key if set
		$routingKey = isset($this->configQueue['routing_key']) ? $this->configQueue['routing_key'] : '';

		// bind queue to the exchange
		$this->channel->queue_bind($name, $this->exchange, $routingKey);
	}

	/**
	 * @param string       $destination
	 * @param DateTime|int $delay
	 *
	 * @return string
	 */
	private function declareDelayedQueue($destination, $delay)
	{
		$delay = $this->getSeconds($delay);
		$destination = $this->getQueueName($destination);
		$name = $this->getQueueName($destination) . '_deferred_' . $delay;

		//Get the routing key if set
		$routingKey = isset($this->configQueue['routing_key']) ? $this->configQueue['routing_key'] : '';

		// declare exchange
		$this->channel->exchange_declare(
			$name,
			$this->configExchange['type'],
			$this->configExchange['passive'],
			$this->configExchange['durable'],
			$this->configExchange['auto_delete']
		);

		// declare queue
		$this->channel->queue_declare(
			$name,
			$this->configQueue['passive'],
			$this->configQueue['durable'],
			$this->configQueue['exclusive'],
			$this->configQueue['auto_delete'],
			false,
			new AMQPTable([
				'x-dead-letter-exchange'    => $this->exchange,
				'x-dead-letter-routing-key' => $routingKey,
				'x-message-ttl'             => $delay * 1000,
			])
		);

		// bind queue to the exchange
		$this->channel->queue_bind($name, $name, $name);

		return $name;
	}

}
