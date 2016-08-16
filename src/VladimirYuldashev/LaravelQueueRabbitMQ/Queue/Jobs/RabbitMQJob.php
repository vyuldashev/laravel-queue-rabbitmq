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
     * @param Illuminate\Container\Container $container
     * @param VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue $connection
     * @param PhpAmqpLib\Channel\AMQPChannel $channel
     * @param string $queue
     * @param PhpAmqpLib\Message\AMQPMessage $message
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
     * Fire the job.
     *
     * @return void
     */
    public function fire()
    {
        $this->resolveAndFire($this->getParsedBody());
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
     * Retrieves the parsed body for the job.
     *
     * @return array|false
     */
    public function getParsedBody()
    {
        return json_decode($this->getRawBody(), true);
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
     * Get the queue name.
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
        $this->setAttempts($this->attempts() + 1);

        $body = $this->getParsedBody();

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
     * @return string
     */
    public function getJobId()
    {
        return $this->message->get('correlation_id');
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
