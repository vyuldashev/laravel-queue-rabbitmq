<?php namespace FintechFab\LaravelQueueRabbitMQ\Queue\Jobs;

use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\Job;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Queue;

class RabbitMQJob extends Job
{

	protected $channel;
	protected $queue;
	protected $message;

	public function __construct(
		Container $container,
		AMQPChannel $channel,
		$queue,
		AMQPMessage $message
	)
	{
		$this->container = $container;
		$this->channel = $channel;
		$this->queue = $queue;
		$this->message = $message;
	}

	/**
	 * Fire the job.
	 *
	 * @return void
	 */
	public function fire()
	{
		$this->resolveAndFire(json_decode($this->message->body, true));
	}

	/**
	 * Get the raw body string for the job.
	 *
	 * @return string
	 */
	public function getRawBody()
	{
		return $this->message->body;
	}

	/**
	 * Delete the job from the queue.
	 *
	 * @return void
	 */
	public function delete()
	{
		parent::delete();

		$this->channel->basic_ack($this->message->delivery_info['delivery_tag']);
	}

	/**
	 * Get queue name
	 *
	 * @return string
	 */
	public function getQueue()
	{
		return $this->queue;
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

		$body = $this->message->body;
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
		$body = json_decode($this->message->body, true);

		return isset($body['data']['attempts']) ? (int)$body['data']['attempts'] : 0;
	}

	/**
	 * Get the job identifier.
	 *
	 * @return string
	 */
	public function getJobId()
	{
		return $this->message->get('correlation_id');
	}

}
