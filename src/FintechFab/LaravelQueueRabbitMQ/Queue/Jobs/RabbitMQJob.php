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
	 * Get the raw body string for the job.
	 *
	 * @return string
	 */
	public function getRawBody()
	{
		return $this->envelope->getBody();
	}

	/**
	 * Delete the job from the queue.
	 *
	 * @return void
	 */
	public function delete()
	{
		parent::delete();
		$this->queue->ack($this->envelope->getDeliveryTag());
	}

	/**
	 * Get queue name
	 *
	 * @return string
	 */
	public function getQueue()
	{
		return $this->queue->getName();
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

		$job = $body['job'];
		$data = $body['data'];

		// push back to a queue
		if ($delay > 0) {
			Queue::later($delay, $job, $data, $this->getQueue());
		} else {
			Queue::push($job, $data, $this->getQueue());
		}
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
