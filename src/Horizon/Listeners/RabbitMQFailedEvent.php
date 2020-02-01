<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Horizon\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed as LaravelJobFailed;
use Laravel\Horizon\Events\JobFailed as HorizonJobFailed;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;

class RabbitMQFailedEvent
{
    /**
     * The event dispatcher implementation.
     *
     * @var Dispatcher
     */
    public $events;

    /**
     * Create a new listener instance.
     *
     * @param Dispatcher $events
     * @return void
     */
    public function __construct(Dispatcher $events)
    {
        $this->events = $events;
    }

    /**
     * Handle the event.
     *
     * @param LaravelJobFailed $event
     * @return void
     */
    public function handle(LaravelJobFailed $event): void
    {
        if (! $event->job instanceof RabbitMQJob) {
            return;
        }

        $this->events->dispatch((new HorizonJobFailed(
            $event->exception,
            $event->job,
            $event->job->getRawBody()
        ))->connection($event->connectionName)->queue($event->job->getQueue()));
    }
}
