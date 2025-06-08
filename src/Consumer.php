<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ;

use Exception;
use Illuminate\Console\Concerns\InteractsWithIO;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Factory as QueueManager;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Output\OutputInterface;
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

    /**
     * @var bool
     */
    protected $blocking = false;

    /**
     * @var bool
     */
    protected $initQueue = false;

    /**
     * @var bool
     */
    protected $autoReconnect = false;

    /**
     * @var float
     */
    protected $autoReconnectPause = 0;

    /**
     * @var float
     */
    protected $aliveCheck = 0;

    /**
     * @var bool
     */
    protected $asyncSignalsSupported;

    /**
     * @var RabbitMQQueue
     */
    protected $connection = null;

    /**
     * @var AMQPChannel
     */
    protected $channel;

    /**
     * @var object|null
     */
    protected $currentJob;

    /**
     * @var InteractsWithIO
     */
    protected $interactsWithIO = null;

    /**
     * {@inheritDoc}
     */
    public function __construct(
        QueueManager $manager,
        Dispatcher $events,
        ExceptionHandler $exceptions,
        callable $isDownForMaintenance,
        ?callable $resetScope = null
    ) {
        parent::__construct($manager, $events, $exceptions, $isDownForMaintenance, $resetScope);
        $this->asyncSignalsSupported = $this->supportsAsyncSignals();
    }

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

    public function setBlocking(bool $value): void
    {
        $this->blocking = $value;
    }

    public function setInitQueue(bool $value): void
    {
        $this->initQueue = $value;
    }

    public function setAutoReconnect(bool $value): void
    {
        $this->autoReconnect = $value;
    }

    public function setAutoReconnectPause(float $value): void
    {
        $this->autoReconnectPause = $value;
    }

    public function setAliveCheck(float $value): void
    {
        $this->aliveCheck = $value;
    }

    /**
     * @param  InteractsWithIO  $value
     */
    public function setInteractWithIO($value): void
    {
        $this->interactsWithIO = $value;
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
        if ($this->asyncSignalsSupported) {
            $this->listenForSignals();
        }

        $startTime = hrtime(true) / 1e9;
        $jobsProcessed = 0;
        $lastRestart = $this->getTimestampOfLastQueueRestart();

        while (true) {
            try {
                $this->printOutputLine('Creating new connection');
                $this->initConnection($connectionName, $queue);
                $status = $this->consume(
                    $startTime,
                    $jobsProcessed,
                    $lastRestart,
                    $connectionName,
                    $queue,
                    $options
                );
                if ($status !== null) {
                    return $status;
                }

                break;
            } catch (AMQPExceptionInterface $exception) {
                $this->exceptions->report($exception);

                if (! $this->isAmqpConnectionException($exception)) {
                    $this->kill(self::EXIT_ERROR, $options);

                    return 0;
                } elseif (! $this->autoReconnect) {
                    $this->kill(self::EXIT_ERROR, $options);

                    return 0;
                }

                $this->sleep($this->autoReconnectPause);
            }
        }

        return 0;
    }

    /**
     * @return int
     *
     * @throws Throwable
     */
    protected function consume(float $startTime, int &$jobsProcessed, ?int $lastRestart, string $connectionName, string $queue, WorkerOptions $options)
    {
        $jobClass = $this->connection->getJobClass();
        $arguments = [];
        if ($this->maxPriority) {
            $arguments['priority'] = ['I', $this->maxPriority];
        }

        while (true) {
            if ($this->blocking && ! $this->daemonShouldRun($options, $connectionName, $queue)) {
                if ($this->asyncSignalsSupported) {
                    pcntl_signal_dispatch();
                }

                $status = $this->pauseWorker($options, $lastRestart);
                if ($status !== null) {
                    return $this->stop($status, $options);
                }

                continue;
            }

            $this->reinitChannelIfNeed($queue);
            $this->channel->basic_consume(
                $queue,
                $this->consumerTag,
                false,
                false,
                false,
                false,
                function (AMQPMessage $message) use ($options, $connectionName, $queue, $jobClass, &$jobsProcessed): void {
                    $this->printOutputLine('New message is received');
                    $job = new $jobClass(
                        $this->container,
                        $this->connection,
                        $message,
                        $connectionName,
                        $queue
                    );

                    $this->currentJob = $job;

                    if ($this->asyncSignalsSupported) {
                        $this->registerTimeoutHandler($job, $options);
                    }

                    $jobsProcessed++;

                    $this->runJob($job, $connectionName, $options);

                    if ($this->asyncSignalsSupported) {
                        $this->resetTimeoutHandler();
                    }

                    if ($options->rest > 0) {
                        $this->sleep($options->rest);
                    }

                    $this->printOutputLine('New message is processed');
                },
                null,
                $arguments
            );

            while ($this->channel->is_consuming()) {
                // Before reserving any jobs, we will make sure this queue is not paused and
                // if it is we will just pause this worker for a given amount of time and
                // make sure we do not need to kill this worker process off completely.
                if (! $this->blocking && ! $this->daemonShouldRun($options, $connectionName, $queue)) {
                    $status = $this->pauseWorker($options, $lastRestart);
                    if ($status !== null) {
                        return $this->stop($status, $options);
                    }

                    continue;
                }

                // If the daemon should run (not in maintenance mode, etc.), then we can wait for a job.
                try {
                    $this->channel->wait(null, ! $this->blocking, $this->blocking ? $this->aliveCheck : $options->timeout);
                } catch (AMQPTimeoutException $exception) {
                    if ($this->blocking && $this->aliveCheck > 0) {
                        $this->checkAlive();
                    } else {
                        throw $exception;
                    }
                } catch (AMQPExceptionInterface $exception) {
                    throw $exception;
                } catch (Throwable $exception) {
                    $this->exceptions->report($exception);

                    $this->stopWorkerIfLostConnection($exception);
                }

                // If no job is got off the queue, we will need to sleep the worker.
                if (! $this->blocking && ! $this->currentJob) {
                    $this->sleep($options->sleep);
                }

                // Finally, we will check to see if we have exceeded our memory limits or if
                // the queue should restart based on other indications. If so, we'll stop
                // this worker and let whatever is "monitoring" it restart the process.
                $status = $this->stopIfNecessary(
                    $options,
                    $lastRestart,
                    $startTime,
                    $jobsProcessed,
                    $this->currentJob
                );
                if ($status !== null) {
                    return $this->stop($status, $options);
                }

                $this->currentJob = null;
            }

            if (! $this->blocking) {
                break;
            }
        }

        return null;
    }

    /**
     * @throws \PhpAmqpLib\Exception\AMQPProtocolChannelException
     */
    protected function initConnection(string $connectionName, string $queue): void
    {
        if ($this->connection !== null) {
            // Reconnecting
            $this->connection->reconnect();
        } else {
            /* @var RabbitMQQueue $connection */
            $this->connection = $this->manager->connection($connectionName);
            if (! $this->connection instanceof RabbitMQQueue) {
                throw new Exception('Connection should implement '.RabbitMQQueue::class);
            }

            // Force disable retrying when we are in the consumer context (to avoid bad state of a job)
            $this->connection->setDisableRetries(true);
        }

        $this->initChannel($queue);
    }

    /**
     * @return void
     *
     * @throws \PhpAmqpLib\Exception\AMQPProtocolChannelException
     */
    protected function initChannel(string $queue)
    {
        if ($this->initQueue) {
            $this->declareQueue($this->connection, $queue);
        }

        $this->channel = $this->connection->getChannel();
        $this->channel->basic_qos(
            $this->prefetchSize,
            $this->prefetchCount,
            null
        );
    }

    /**
     * @return bool
     *
     * @throws \PhpAmqpLib\Exception\AMQPProtocolChannelException
     */
    protected function reinitChannelIfNeed(string $queue)
    {
        // Check that channel is active
        // Connection can close channel because of reconnection mechanism itself
        if ($this->channel->is_open()) {
            return false;
        }

        $this->initChannel($queue);

        return true;
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
     * {@inheritdoc}
     */
    protected function runJob($job, $connectionName, WorkerOptions $options)
    {
        if (! $this->blocking) {
            return parent::runJob($job, $connectionName, $options);
        }

        try {
            return $this->process($connectionName, $job, $options);
        } catch (Throwable $e) {
            // Throw exception to call reconnect
            if ($this->isAmqpConnectionException($e)) {
                throw $e;
            }

            $this->exceptions->report($e);

            $this->stopWorkerIfLostConnection($e);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function listenForSignals()
    {
        if (! $this->blocking) {
            parent::listenForSignals();

            return;
        }

        // Support pause/exit for blocking mode
        pcntl_async_signals(true);
        pcntl_signal(SIGHUP, [$this, 'quitSignalHandler']);
        pcntl_signal(SIGTERM, [$this, 'quitSignalHandler']);
        pcntl_signal(SIGQUIT, [$this, 'quitSignalHandler']);

        pcntl_signal(SIGUSR2, function () {
            $this->printOutputLine('SIGUSR2('.SIGUSR2.') is received. Pause this worker', 'warning');
            $this->paused = true;
            $this->channel->basic_cancel($this->consumerTag);
        });

        pcntl_signal(SIGCONT, function () {
            $this->printOutputLine('SIGCONT('.SIGCONT.') is received. Unpause this worker', 'warning');
            $this->paused = false;
        });
    }

    /**
     * @param  null  $sigInfo
     */
    public function quitSignalHandler($signal, $sigInfo = null): void
    {
        $signalName = null;
        switch ($signal) {
            case SIGHUP:
                $signalName = 'SIGHUP';
                break;
            case SIGTERM:
                $signalName = 'SIGTERM';
                break;
            case SIGQUIT:
                $signalName = 'SIGQUIT';
                break;
        }

        $this->printOutputLine("$signalName($signal) is received. Activate the `shouldQuit` option", 'warning');
        $this->shouldQuit = true;
        $this->channel->basic_cancel($this->consumerTag);
    }

    /**
     * @throws AMQPProtocolChannelException
     */
    protected function declareQueue(RabbitMQQueue $queue, ?string $queueName): void
    {
        // When the queue already exists, just return.
        if ($queue->isQueueExists($queueName)) {
            return;
        }

        // Create a queue
        $queue->declareQueueByConfig($queueName);
    }

    /**
     * {@inheritDoc}
     */
    public function stop($status = 0, $options = null)
    {
        if (is_array($status)) {
            [$status, $reason] = $status;
        } else {
            $reason = null;
        }

        $reason = $reason ?: 'no reason';
        $this->printOutputLine('Stopping this worker with status '.$status.' because of '.$reason, 'error');

        // Tell the server you are going to stop consuming.
        // It will finish up the last message and not send you anymore.
        $this->channel->basic_cancel($this->consumerTag, false, true);

        return parent::stop($status, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function kill($status = 0, $options = null)
    {
        $this->printOutputLine('Killing this worker with status '.$status, 'error');
        parent::kill($status, $options);
    }

    /**
     * Same as parent, but with logs.
     * {@inheritDoc}
     */
    public function stopIfNecessary(WorkerOptions $options, $lastRestart, $startTime = 0, $jobsProcessed = 0, $job = null)
    {
        return match (true) {
            $this->shouldQuit => [static::EXIT_SUCCESS, '`shouldQuit` is active'],
            $this->memoryExceeded($options->memory) => [static::EXIT_MEMORY_LIMIT, 'exceeded memory-limit'],
            $this->queueShouldRestart($lastRestart) => [static::EXIT_SUCCESS, 'should-restart'],
            $options->stopWhenEmpty && is_null($job) => [static::EXIT_SUCCESS, '`stopWhenEmpty` option is active'],
            $options->maxTime && hrtime(true) / 1e9 - $startTime >= $options->maxTime => [static::EXIT_SUCCESS, 'reached max working time'],
            $options->maxJobs && $jobsProcessed >= $options->maxJobs => [static::EXIT_SUCCESS, 'reached max processed jobs'],
            default => null
        };
    }

    /**
     * Same as parent, but with logs.
     * {@inheritDoc}
     */
    protected function registerTimeoutHandler($job, WorkerOptions $options)
    {
        // We will register a signal handler for the alarm signal so that we can kill this
        // process if it is running too long because it has frozen. This uses the async
        // signals supported in recent versions of PHP to accomplish it conveniently.
        pcntl_signal(SIGALRM, function () use ($job, $options) {
            $this->printOutputLine('SIGALRM('.SIGALRM.') is received (timeout). Cancelling a job and killing this worker', 'error');

            if ($job) {
                $this->markJobAsFailedIfWillExceedMaxAttempts(
                    $job->getConnectionName(), $job, (int) $options->maxTries, $e = $this->timeoutExceededException($job)
                );

                $this->markJobAsFailedIfWillExceedMaxExceptions(
                    $job->getConnectionName(), $job, $e
                );

                $this->markJobAsFailedIfItShouldFailOnTimeout(
                    $job->getConnectionName(), $job, $e
                );

                $this->events->dispatch(new JobTimedOut(
                    $job->getConnectionName(), $job
                ));
            }

            $this->kill(static::EXIT_ERROR, $options);
        }, true);

        pcntl_alarm(
            max($this->timeoutForJob($job, $options), 0)
        );
    }

    /**
     * @return bool
     */
    protected function printOutputLine(string $message, string $type = 'comment')
    {
        if (! $this->interactsWithIO) {
            return false;
        }

        $message = sprintf(
            '[%s] %s',
            date('Y-m-d H:i:s.u'),
            $message
        );
        switch ($type) {
            case 'comment':
                $this->interactsWithIO->line($message, null, OutputInterface::VERBOSITY_VERBOSE);
                break;
            case 'warning':
                $this->interactsWithIO->warn($message, OutputInterface::VERBOSITY_NORMAL);
                break;
            default:
                $this->interactsWithIO->error($message, OutputInterface::VERBOSITY_NORMAL);
                break;
        }

        return true;
    }

    protected function isAmqpConnectionException(Throwable $exception): bool
    {
        return $exception instanceof AMQPExceptionInterface;
    }

    protected function checkAlive(): void
    {
        try {
            // We need to call any command that has a response, and we can wait for it.
            // If there is no response, we consider the current connection dead
            // WARNING: It works ONLY if `channel_rpc_timeout` is greater than 0
            $this->channel->basic_qos(
                $this->prefetchSize,
                $this->prefetchCount,
                null
            );
        } catch (AMQPTimeoutException $exception) {
            throw new AMQPTimeoutException('Custom alive check failed', $exception->getTimeout(), $exception->getCode(), $exception);
        }
    }
}
