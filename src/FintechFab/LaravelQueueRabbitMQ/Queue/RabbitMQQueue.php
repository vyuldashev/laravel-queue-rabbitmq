<?php namespace FintechFab\LaravelQueueRabbitMQ\Queue;

use AMQPChannel;
use AMQPConnection;
use AMQPEnvelope;
use AMQPException;
use AMQPExchange;
use AMQPQueue;
use FintechFab\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;

class RabbitMQQueue extends Queue implements QueueContract
{

	protected $connection;
	protected $channel;
	protected $exchange;
	protected $default_queue;
	protected $exchange_name;
	protected $exchange_type;
	protected $exchange_flags;

	/**
	 * @param AMQPConnection $amqpConnection
	 * @param string         $queueName
	 * @param string         $exchangeName
	 *
	 * @param mixed          $exchangeType
	 * @param mixed          $exchangeFlags
	 */
	public function __construct(AMQPConnection $amqpConnection, $queueName, $exchangeName, $exchangeType, $exchangeFlags)
	{
		$this->connection = $amqpConnection;
		$this->default_queue = $queueName;
		$this->exchange_name = $exchangeName;
		$this->exchange_type = $exchangeType;
		$this->exchange_flags = $exchangeFlags;
		$this->channel = $this->getChannel();
		$this->exchange = $this->getExchange($this->channel);
	}

	/**
	 * Push a new job onto the queue.
	 *
	 * @param  string $job
	 * @param  mixed  $data
	 * @param  string $queue
	 *
	 * @throws AMQPException
	 * @return bool
	 */
	public function push($job, $data = '', $queue = null)
	{
		$payload = $this->createPayload($job, $data);

		// get queue
		$queue = $this->declareQueue($queue);

		// push task to a queue
		$job = $this->exchange->publish($payload, $queue->getName());

		if (!$job) {
			throw new AMQPException('Could not push job to a queue');
		}

		return $job;
	}

	/**
	 * Push a raw payload onto the queue.
	 *
	 * @param  string $payload
	 * @param  string $queue
	 * @param  array  $options
	 *
	 * @throws \AMQPException
	 * @return mixed
	 */
	public function pushRaw($payload, $queue = null, array $options = [])
	{
		// get queue
		$queue = $this->declareQueue($queue);

		// push task to a queue
		$job = $this->exchange->publish($payload, $queue->getName());

		if (!$job) {
			throw new AMQPException('Could not push job to a queue');
		}

		return $job;
	}

	/**
	 * Push a new job onto the queue after a delay.
	 *
	 * @param  \DateTime|int $delay
	 * @param  string        $job
	 * @param  mixed         $data
	 * @param  string        $queue
	 *
	 * @throws \AMQPException
	 * @return mixed
	 */
	public function later($delay, $job, $data = '', $queue = null)
	{
		$payload = $this->createPayload($job, $data);

		// declare queues if they do not exist
		$this->declareQueue($queue);
		$queue = $this->declareDelayedQueue($queue, $delay);

		$job = $this->exchange->publish($payload, $queue->getName());

		if (!$job) {
			throw new AMQPException('Could not push job to a queue');
		}

		return $job;
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
		$queue = $this->declareQueue($queue);

		// get envelope
		$envelope = $queue->get();

		if ($envelope instanceof AMQPEnvelope) {
			return new RabbitMQJob($this->container, $queue, $envelope);
		}

		return null;
	}

	/**
	 * @param $queue
	 *
	 * @return string
	 */
	private function getQueueName($queue)
	{
		return $queue ?: $this->default_queue;
	}

	/**
	 * @return AMQPChannel
	 */
	private function getChannel()
	{
		return new AMQPChannel($this->connection);
	}

	/**
	 * @return AMQPExchange
	 */
	private function getCurrentExchange()
	{
		return $this->exchange;
	}

	/**
	 * @param AMQPChannel $channel
	 *
	 * @return AMQPExchange
	 */
	private function getExchange(AMQPChannel $channel)
	{
		$exchange = new AMQPExchange($channel);
		$exchange->setName($this->exchange_name);
		$exchange->setFlags($this->exchange_flags);
		$exchange->setType($this->exchange_type);
		$exchange->declareExchange();

		return $exchange;
	}

	/**
	 * @param string $name
	 *
	 * @return AMQPQueue
	 */
	private function declareQueue($name)
	{
		$name = $this->getQueueName($name);

		$queue = new AMQPQueue($this->channel);
		$queue->setName($name);
		$queue->setFlags(AMQP_DURABLE);
		$queue->declareQueue();

		$queue->bind($this->exchange->getName(), $name);

		$queue->declareQueue();

		return $queue;
	}

	/**
	 * @param string $destination
	 *
	 * @param int    $delay
	 *
	 * @return AMQPQueue
	 */
	private function declareDelayedQueue($destination, $delay)
	{
		$destination = $this->getQueueName($destination);
		$name = $destination . '_deferred_' . $delay;

		$queue = new AMQPQueue($this->channel);
		$queue->setName($name);
		$queue->setFlags(AMQP_DURABLE);
		$queue->setArguments([
			'x-dead-letter-exchange'    => $this->exchange->getName(),
			'x-dead-letter-routing-key' => $destination,
			'x-message-ttl'             => $delay * 1000,
		]);

		$queue->declareQueue();

		$queue->bind($this->exchange->getName(), $name);

		$queue->declareQueue();

		return $queue;
	}

}
