<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQJob extends Job implements JobContract
{
    /**
     * Same as RabbitMQQueue, used for attempt counts.
     */
    const ATTEMPT_COUNT_HEADERS_KEY = 'attempts_count';

    protected $connection;
    protected $channel;
    protected $queue;
    protected $message;

    /**
     * Creates a new instance of RabbitMQJob.
     *
     * @param \Illuminate\Container\Container $container
     * @param \VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue $connection
     * @param \PhpAmqpLib\Channel\AMQPChannel $channel
     * @param string $queue
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public function __construct(
        Container $container,
        RabbitMQQueue $connection,
        AMQPChannel $channel,
        $queue,
        AMQPMessage $message
    ) {
        $this->container = $container;
        $this->connection = $connection;
        $this->channel = $channel;
        $this->queue = $queue;
        $this->message = $message;
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        if ($this->message->has('application_headers') === true) {
            $headers = $this->message->get('application_headers')->getNativeData();

            if (isset($headers[self::ATTEMPT_COUNT_HEADERS_KEY]) === true) {
                return $headers[self::ATTEMPT_COUNT_HEADERS_KEY];
            }
        }

        return 0;
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
        $this->channel->basic_ack($this->message->get('delivery_tag'));
    }

    /**
     * Release the job back into the queue.
     *
     * @param int $delay
     *
     * @return void
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        $this->delete();
        $this->setAttempts($this->attempts() + 1);

        $body = $this->payload();

        /*
         * Some jobs don't have the command set, so fall back to just sending it the job name string
         */
        if (isset($body['data']['command']) === true) {
            $job = unserialize($body['data']['command']);
        } else {
            $job = $this->getName();
        }

        $data = $body['data'];

        if ($delay > 0) {
            $this->connection->later($delay, $job, $data, $this->getQueue());
        } else {
            $this->connection->push($job, $data, $this->getQueue());
        }
    }

    /**
     * Sets the count of attempts at processing this job.
     *
     * @param int $count
     *
     * @return void
     */
    private function setAttempts($count)
    {
        $this->connection->setAttempts($count);
    }

    /**
     * Get the job identifier.
     *
     * @return string|false
     */
    public function getJobId()
    {
        if ($this->message->has('correlation_id') === true) {
            return $this->message->get('correlation_id');
        }

        return false;
    }

    /**
     * Sets the job identifier.
     *
     * @param string $id
     *
     * @return void
     */
    public function setJobId($id)
    {
        $this->connection->setCorrelationId($id);
    }
}
