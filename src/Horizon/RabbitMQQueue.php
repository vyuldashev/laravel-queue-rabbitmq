<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Horizon;

use Laravel\Horizon\JobId;
use Laravel\Horizon\JobPayload;
use Laravel\Horizon\Events\JobPushed;
use Laravel\Horizon\Events\JobDeleted;
use Laravel\Horizon\Events\JobReserved;
use Illuminate\Contracts\Events\Dispatcher;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue as BaseRabbitMQQueue;

class RabbitMQQueue extends BaseRabbitMQQueue
{
    /**
     * The job that last pushed to queue via the "push" method.
     *
     * @var object|string
     */
    protected $lastPushed;

    /**
     * Get the number of queue jobs that are ready to process.
     *
     * @param  string|null $queue
     * @return int
     */
    public function readyNow($queue = null): int
    {
        return $this->size($queue);
    }

    /** {@inheritdoc} */
    public function push($job, $data = '', $queue = null)
    {
        $this->lastPushed = $job;

        return parent::push($job, $data, $queue);
    }

    /** {@inheritdoc} */
    public function pushRaw($payload, $queueName = null, array $options = [])
    {
        $payload = (new JobPayload($payload))->prepare($this->lastPushed)->value;

        return tap(parent::pushRaw($payload, $queueName, $options), function () use ($queueName, $payload) {
            $this->event($this->getQueueName($queueName), new JobPushed($payload));
        });
    }

    /** {@inheritdoc} */
    public function later($delay, $job, $data = '', $queueName = null)
    {
        $payload = (new JobPayload($this->createPayload($job, $data)))->prepare($job)->value;

        return tap(parent::pushRaw($payload, $queueName, ['delay' => $this->secondsUntil($delay)]), function () use ($payload, $queueName) {
            $this->event($this->getQueueName($queueName), new JobPushed($payload));
        });
    }

    /** {@inheritdoc} */
    public function pop($queueName = null)
    {
        return tap(parent::pop($queueName), function ($result) use ($queueName) {
            if ($result instanceof RabbitMQJob) {
                $this->event($queueName ?: $this->queueName, new JobReserved($result->getRawBody()));
            }
        });
    }

    /** {@inheritdoc} */
    public function release($delay, $job, $data, $queue, $attempts = 0)
    {
        $this->lastPushed = $job;

        return parent::release($delay, $job, $data, $queue, $attempts);
    }

    /**
     * Fire the job deleted event.
     *
     * @param  string $queueName
     * @param  RabbitMQJob $job
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function deleteReserved($queueName, $job): void
    {
        $this->event($this->getQueueName($queueName), new JobDeleted($job, $job->getRawBody()));
    }

    /**
     * Fire the given event if a dispatcher is bound.
     *
     * @param  string $queue
     * @param  mixed $event
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function event($queue, $event): void
    {
        if ($this->container && $this->container->bound(Dispatcher::class)) {
            $this->container->make(Dispatcher::class)->dispatch(
                $event->connection($this->getConnectionName())->queue($queue)
            );
        }
    }

    /** {@inheritdoc} */
    protected function getRandomId(): string
    {
        return JobId::generate();
    }
}
