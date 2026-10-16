<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Message\AMQPMessage;
use ReflectionMethod;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class Consumer extends Worker
{
    /** @var Container */
    protected $container;

    /** @var string */
    protected $consumerTag;

    /** @var int */
    protected $prefetchSize;

    /** @var int */
    protected $maxPriority;

    /** @var int */
    protected $prefetchCount;

    /** @var AMQPChannel */
    protected $channel;

    public function setContainer(Container $value): void
    {
        $this->container = $value;
    }

    public function setConsumerTag(string $value): void
    {
        $this->consumerTag = $value;
    }

    public function setMaxPriority(int $value): void
    {
        $this->maxPriority = $value;
    }

    public function setPrefetchSize(int $value): void
    {
        $this->prefetchSize = $value;
    }

    public function setPrefetchCount(int $value): void
    {
        $this->prefetchCount = $value;
    }

    /**
     * Listen to the given queue in a loop.
     *
     * @param  string  $connectionName
     * @param  string  $queue
     * @return int
     *
     * @throws Throwable
     */
    public function daemon($connectionName, $queue, WorkerOptions $options)
    {
        if ($this->supportsAsyncSignals()) {
            $this->listenForSignals();
        }

        $lastRestart = $this->getTimestampOfLastQueueRestart();

        [$startTime, $jobsProcessed, $lastJobProcessedAt] = [hrtime(true) / 1e9, 0, null];

        /** @var RabbitMQQueue $connection */
        $connection = $this->manager->connection($connectionName);

        $this->channel = $connection->getChannel();

        $this->channel->basic_qos(
            $this->prefetchSize,
            $this->prefetchCount,
            false
        );

        $jobClass = $connection->getJobClass();
        $arguments = [];
        if ($this->maxPriority) {
            $arguments['priority'] = ['I', $this->maxPriority];
        }

        // Tracked locally instead of on $this to avoid two problems:
        //   1. Illuminate\Queue\Worker::$currentJob became public in Laravel 13.7,
        //      so redeclaring it on Consumer hits a visibility narrowing error.
        //   2. Worker::runJob() may reset $this->currentJob to null after each job,
        //      which made the "if (currentJob === null) sleep" branch trigger after
        //      every successful job and starved throughput (see #661).
        $currentJob = null;

        // Laravel 13.31 added the connection and queue names as the first two
        // parameters of Worker::registerTimeoutHandler().
        $timeoutHandlerTakesQueue = (new ReflectionMethod(Worker::class, 'registerTimeoutHandler'))
            ->getParameters()[0]->getName() === 'connectionName';

        // Laravel 13.18 keeps the processed jobs count and the time of the last job on the
        // worker instead of taking them as arguments of Worker::stopIfNecessary(). Worker::kill()
        // reads them too, so they are updated around each job, like Worker::daemon() does.
        $workerTracksJobs = property_exists(Worker::class, 'jobsProcessed');

        if ($workerTracksJobs) {
            $this->jobsProcessed = 0;
        }

        $this->channel->basic_consume(
            $queue,
            $this->consumerTag,
            false,
            false,
            false,
            false,
            function (AMQPMessage $message) use ($connection, $options, $connectionName, $queue, $jobClass, $timeoutHandlerTakesQueue, $workerTracksJobs, &$jobsProcessed, &$lastJobProcessedAt, &$currentJob): void {
                $job = new $jobClass(
                    $this->container,
                    $connection,
                    $message,
                    $connectionName,
                    $queue
                );

                $currentJob = $job;

                if ($this->supportsAsyncSignals()) {
                    if ($timeoutHandlerTakesQueue) {
                        $this->registerTimeoutHandler($connectionName, $queue, $job, $options);
                    } else {
                        $this->registerTimeoutHandler($job, $options);
                    }
                }

                if ($workerTracksJobs) {
                    $this->jobsProcessed++;
                } else {
                    $jobsProcessed++;
                }

                $this->runJob($job, $connectionName, $options);

                if ($workerTracksJobs) {
                    $this->lastJobProcessedAt = hrtime(true) / 1e9;
                } else {
                    $lastJobProcessedAt = hrtime(true) / 1e9;
                }

                if ($this->supportsAsyncSignals()) {
                    $this->resetTimeoutHandler();
                }

                if ($options->rest > 0) {
                    $this->sleep($options->rest);
                }
            },
            null,
            $arguments
        );

        while ($this->channel->is_consuming()) {
            // Before reserving any jobs, we will make sure this queue is not paused and
            // if it is we will just pause this worker for a given amount of time and
            // make sure we do not need to kill this worker process off completely.
            if (! $this->daemonShouldRun($options, $connectionName, $queue)) {
                // Like Worker::pauseWorker(): sleep, then check whether the worker should stop, so
                // that a paused worker still stops on SIGTERM, queue:restart and the limits.
                // pauseWorker() itself is not called: some Laravel versions don't pass it the
                // start time, and --max-time would then stop the worker at once.
                $this->sleep($options->sleep > 0 ? $options->sleep : 1);

                [$status, $reason] = $this->shouldStop($options, $lastRestart, $startTime, $jobsProcessed, $currentJob, $lastJobProcessedAt, $workerTracksJobs);

                if (! is_null($status)) {
                    return $this->stop($status, $options, $reason);
                }

                continue;
            }

            // If the daemon should run (not in maintenance mode, etc.), then we can wait for a job.
            try {
                $this->channel->wait(null, true, (int) $options->timeout);
            } catch (AMQPRuntimeException $exception) {
                $this->exceptions->report($exception);

                $this->kill(self::EXIT_ERROR, $options);
            } catch (Exception|Throwable $exception) {
                $this->exceptions->report($exception);

                $this->stopWorkerIfLostConnection($exception);
            }

            // If no job was consumed during this wait() cycle, sleep the worker.
            if ($currentJob === null) {
                $this->sleep($options->sleep);
            }

            // Finally, we will check to see if we have exceeded our memory limits or if
            // the queue should restart based on other indications. If so, we'll stop
            // this worker and let whatever is "monitoring" it restart the process.
            [$status, $reason] = $this->shouldStop($options, $lastRestart, $startTime, $jobsProcessed, $currentJob, $lastJobProcessedAt, $workerTracksJobs);

            if (! is_null($status)) {
                return $this->stop($status, $options, $reason);
            }

            $currentJob = null;
        }
    }

    /**
     * Ask Worker::stopIfNecessary() whether the worker should stop, in the form that the
     * installed Laravel version expects.
     *
     * @return array{0: int|null, 1: mixed} the exit status, or null to go on, and the stop reason
     */
    protected function shouldStop(WorkerOptions $options, $lastRestart, float $startTime, int $jobsProcessed, $currentJob, ?float $lastJobProcessedAt, bool $workerTracksJobs): array
    {
        if ($workerTracksJobs) {
            $result = $this->stopIfNecessary($options, $lastRestart, $startTime, $currentJob);
        } else {
            // Laravel 11.53, 12.60 and 13.10 added the last argument for --stop-when-empty-for;
            // older versions ignore it.
            $result = $this->stopIfNecessary($options, $lastRestart, $startTime, $jobsProcessed, $currentJob, $lastJobProcessedAt);
        }

        // Laravel 11.52+, 12.59+ and 13.x return the exit status together with the stop reason.
        return is_array($result) ? $result : [$result, null];
    }

    /**
     * Determine if the daemon should process on this iteration.
     *
     * @param  string  $connectionName
     * @param  string  $queue
     */
    protected function daemonShouldRun(WorkerOptions $options, $connectionName, $queue): bool
    {
        return ! ((($this->isDownForMaintenance)() && ! $options->force) || $this->paused);
    }

    /**
     * Stop listening and bail out of the script.
     *
     * @param  int  $status
     * @param  WorkerOptions|null  $options
     * @param  string|null  $reason
     * @return int
     */
    public function stop($status = 0, $options = null, $reason = null, $connectionName = null, $queue = null)
    {
        // Tell the server you are going to stop consuming.
        // It will finish up the last message and not send you any more.
        $this->channel->basic_cancel($this->consumerTag, false, true);

        return parent::stop($status, $options, $reason);
    }
}
