<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class Consumer extends Worker
{
    /** @var Container */
    protected $container;

    /** @var string */
    protected $consumerTag;

    /** @var bool */
    protected $noLocal;

    /** @var bool */
    protected $noAck;

    /** @var bool */
    protected $exclusive;

    /** @var int */
    protected $prefetchSize;

    /** @var int */
    protected $prefetchCount;

    public function setContainer(Container $value): void
    {
        $this->container = $value;
    }

    public function setConsumerTag(string $value): void
    {
        $this->consumerTag = $value;
    }

    public function setNoLocal(bool $value): void
    {
        $this->noLocal = $value;
    }

    public function setNoAck(bool $value): void
    {
        $this->noAck = $value;
    }

    public function setExclusive(bool $value): void
    {
        $this->exclusive = $value;
    }

    public function setPrefetchSize(int $value): void
    {
        $this->prefetchSize = $value;
    }

    public function setPrefetchCount(int $value): void
    {
        $this->prefetchCount = $value;
    }

    public function daemon($connectionName, $queue, WorkerOptions $options): void
    {
        if ($this->supportsAsyncSignals()) {
            $this->listenForSignals();
        }

        $lastRestart = $this->getTimestampOfLastQueueRestart();

        /** @var RabbitMQQueue $connection */
        $connection = $this->manager->connection($connectionName);

        $channel = $connection->getChannel();

        $channel->basic_qos(
            $this->prefetchSize,
            $this->prefetchCount,
            null
        );

        $channel->basic_consume(
            $queue,
            $this->consumerTag,
            $this->noLocal,
            $this->noAck,
            $this->exclusive,
            false,
            function (AMQPMessage $message) use ($connection, $options, $connectionName, $queue): void {
                $job = new RabbitMQJob(
                    $this->container,
                    $connection,
                    $message,
                    $connectionName,
                    $queue
                );

                if ($this->supportsAsyncSignals()) {
                    $this->registerTimeoutHandler($job, $options);
                }

                $this->runJob($job, $connectionName, $options);
            }
        );

        while ($channel->is_consuming()) {
            // Before reserving any jobs, we will make sure this queue is not paused and
            // if it is we will just pause this worker for a given amount of time and
            // make sure we do not need to kill this worker process off completely.
            if (! $this->daemonShouldRun($options, $connectionName, $queue)) {
                $this->pauseWorker($options, $lastRestart);
                continue;
            }

            // If the daemon should run (not in maintenance mode, etc.), then we can run
            // fire off this job for processing. Otherwise, we will need to sleep the
            // worker so no more jobs are processed until they should be processed.
            try {
                $channel->wait(null, true, $options->timeout);
            } catch (AMQPTimeoutException $exception) {
                $this->exceptions->report($exception);

                $this->kill(1);
            } catch (Exception $exception) {
                $this->exceptions->report($exception);

                $this->stopWorkerIfLostConnection($exception);
            } catch (Throwable $exception) {
                $this->exceptions->report($exception = new FatalThrowableError($exception));

                $this->stopWorkerIfLostConnection($exception);
            }

            // Finally, we will check to see if we have exceeded our memory limits or if
            // the queue should restart based on other indications. If so, we'll stop
            // this worker and let whatever is "monitoring" it restart the process.
            $this->stopIfNecessary($options, $lastRestart, null);
        }
    }

    /**
     * Determine if the daemon should process on this iteration.
     *
     * @param WorkerOptions $options
     * @param string $connectionName
     * @param string $queue
     * @return bool
     */
    protected function daemonShouldRun(WorkerOptions $options, $connectionName, $queue): bool
    {
        return ! ((($this->isDownForMaintenance)() && ! $options->force) || $this->paused);
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * @param string $connectionName
     * @param Job|RabbitMQJob $job
     * @param int $maxTries
     * @param Exception $e
     */
    protected function markJobAsFailedIfWillExceedMaxAttempts($connectionName, $job, $maxTries, $e): void
    {
        parent::markJobAsFailedIfWillExceedMaxAttempts($connectionName, $job, $maxTries, $e);

        if (! $job->isDeletedOrReleased()) {
            $job->getRabbitMQ()->reject($job);
        }
    }
}
