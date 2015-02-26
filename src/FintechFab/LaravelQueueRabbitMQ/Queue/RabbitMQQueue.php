<?php namespace FintechFab\LaravelQueueRabbitMQ\Queue;

use DateTime;
use FintechFab\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;
use Illuminate\Queue\Queue;
use Illuminate\Queue\QueueInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQQueue extends Queue implements QueueInterface
{

	protected $connection;
	protected $channel;

	protected $configQueue;
	protected $configExchange;

	/**
	 * @param AMQPConnection $amqpConnection
	 * @param array          $config
	 */
	public function __construct(AMQPConnection $amqpConnection, $config)
	{
		$this->connection = $amqpConnection;
		$this->configQueue = $config['queue'];
		$this->configExchange = $config['exchange'];

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
		$payload = $this->createPayload($job, $data);
		$this->declareQueue($queue);

		// push job to a queue
		$message = new AMQPMessage($payload, [
			'Content-Type'  => 'application/json',
			'delivery_mode' => 2,
		]);

		$this->channel->basic_publish($message, $this->configExchange['name']);

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
		$this->declareQueue($queue);

		// push job to a queue
		$message = new AMQPMessage($payload, [
			'Content-Type'  => 'application/json',
			'delivery_mode' => 2,
		]);

		// push task to a queue
		$this->channel->basic_publish($message, $this->configExchange['name']);

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
		$queue = $this->declareDelayedQueue($queue, $delay);

		// push job to a queue
		$message = new AMQPMessage($payload, [
			'Content-Type'  => 'application/json',
			'delivery_mode' => 2,
		]);

		$this->channel->basic_publish($message, $queue);

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
	 * @param $queue
	 *
	 * @return string
	 */
	public function getQueueName($queue)
	{
		return $queue ?: $this->configQueue['name'];
	}

	/**
	 * @return AMQPChannel
	 */
	public function getChannel()
	{
		return $this->connection->channel();
	}

	/**
	 * @param string $name
	 */
	public function declareQueue($name)
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
			$this->configExchange['name'],
			$this->configExchange['type'],
			$this->configExchange['passive'],
			$this->configExchange['durable'],
			$this->configExchange['auto_delete']
		);

		// bind queue to the exchange
		$this->channel->queue_bind($name, $this->configExchange['name'], $name);
	}

	/**
	 * @param string       $destination
	 * @param DateTime|int $delay
	 *
	 * @return string
	 */
	public function declareDelayedQueue($destination, $delay)
	{
		$delay = $this->getSeconds($delay);
		$destination = $this->getQueueName($destination);
		$name = $this->getQueueName($destination) . '_deferred_' . $delay;

		// declare queue
		$this->channel->queue_declare(
			$name,
			$this->configQueue['passive'],
			$this->configQueue['durable'],
			$this->configQueue['exclusive'],
			$this->configQueue['auto_delete'],
			false,
			new AMQPTable([
				'x-dead-letter-exchange' => $destination,
				'x-message-ttl'             => $delay * 1000,
			])
		);

		$this->channel->queue_bind($name, $this->configExchange['name'], $name);

		return $name;
	}

}