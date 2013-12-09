<?php namespace FintechFab\LaravelQueueRabbitMQ\Queue\Jobs;

use AMQPEnvelope;
use AMQPQueue;
use Illuminate\Queue\Jobs\Job;
use Queue;

class RabbitMQJob extends Job
{

	protected $queue;
	protected $envelope;

	public function __construct($container, AMQPQueue $queue, AMQPEnvelope $envelope)
	{
		$this->container = $container;
		$this->queue = $queue;
		$this->envelope = $envelope;
	}

	/**
	 * Fire the job.
	 *
	 * @return void
	 */
	public function fire()
	{
		$this->resolveAndFire(json_decode($this->envelope->getBody(), true));
	}

	/**
	 * Delete the job from the queue.
	 *
	 * @return void
	 */
	public function delete()
	{
		$this->queue->ack($this->envelope->getDeliveryTag());
	}

	/**
	 * Release the job back into the queue.
	 *
	 * @param  int $delay
	 *
	 * @return void
	 */
	public function release($delay = 0)
	{
		$this->delete();

		$body = $this->envelope->getBody();
		$body = json_decode($body, true);

		$attempts = $this->attempts();

		// write attempts to body
		$body['data']['attempts'] = $attempts + 1;

		// push back to a queue
		Queue::push($body['job'], $body['data']);
	}

	/**
	 * Get the number of times the job has been attempted.
	 *
	 * @return int
	 */
	public function attempts()
	{
		$body = json_decode($this->envelope->getBody(), true);

		return isset($body['data']['attempts']) ? $body['data']['attempts'] : 0;
	}

	/**
	 * Get the job identifier.
	 *
	 * @return string
	 */
	public function getJobId()
	{
		return $this->envelope->getMessageId();
	}

}