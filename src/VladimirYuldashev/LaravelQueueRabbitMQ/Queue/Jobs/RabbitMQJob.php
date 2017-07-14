<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs;

use Illuminate\Queue\Jobs\Job;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Queue;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQJob extends Job
{
    protected $channel;
    protected $queue;
    protected $connection;
    protected $message;

    public function __construct($container, RabbitMQQueue $connection, AMQPChannel $channel, $queue, AMQPMessage $message)
    {
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
        $body['attempts'] = $attempts + 1;

        $this->connection->pushRaw(json_encode($body), $this->getQueue(),
            $delay > 0 ? [ 'delay' => $delay ] : []);
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        $body = json_decode($this->message->body, true);

        return isset($body['attempts']) ? $body['attempts'] : 0;
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->message->body;
    }
}
