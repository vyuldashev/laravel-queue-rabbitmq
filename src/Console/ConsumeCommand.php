<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Console;

use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Str;
use VladimirYuldashev\LaravelQueueRabbitMQ\Consumer;

class ConsumeCommand extends WorkCommand
{
    protected $signature = 'rabbitmq:consume
                            {connection? : The name of the queue connection to work}
                            {--name=default : The name of the consumer}
                            {--queue= : The name of the queue to work. Please notice that there is no support for multiple queues}
                            {--once : Only process the next job on the queue}
                            {--stop-when-empty : Stop when the queue is empty}
                            {--delay=0 : The number of seconds to delay failed jobs (Deprecated)}
                            {--backoff=0 : The number of seconds to wait before retrying a job that encountered an uncaught exception}
                            {--max-jobs=0 : The number of jobs to process before stopping}
                            {--max-time=0 : The maximum number of seconds the worker should run}
                            {--force : Force the worker to run even in maintenance mode}
                            {--memory=128 : The memory limit in megabytes}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--rest=0 : Number of seconds to rest between jobs}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=1 : Number of times to attempt a job before logging it failed}
                            {--json : Output the queue worker information as JSON}

                            {--max-priority=}
                            {--consumer-tag}
                            {--prefetch-size=0}
                            {--prefetch-count=1000}
                            {--blocking=0 : Weather to block queue waiting or not}
                            {--init-queue=0 : Enables init the queue before starting consuming}
                            {--auto-reconnect=0 : Enables auto-reconnection when something is wrong with the connection}
                            {--auto-reconnect-pause=0.5 : The pause (in seconds) before reconnecting}
                            {--alive-check=0 : The pause (in seconds) before reconnecting}
                            {--verbose-messages=0 : Write messages only when verbose mode is enabled}
                           ';

    protected $description = 'Consume messages';

    protected $useVerboseForMessages = false;

    public function handle(): void
    {
        /** @var Consumer $consumer */
        $consumer = $this->worker;

        $consumer->setContainer($this->laravel);
        $consumer->setName($this->option('name'));
        $consumer->setConsumerTag($this->consumerTag());
        $consumer->setMaxPriority((int) $this->option('max-priority'));
        $consumer->setPrefetchSize((int) $this->option('prefetch-size'));
        $consumer->setPrefetchCount((int) $this->option('prefetch-count'));
        $consumer->setBlocking($this->booleanOption('blocking'));
        $consumer->setInitQueue($this->booleanOption('init-queue'));
        $consumer->setAutoReconnect($this->booleanOption('auto-reconnect'));
        $consumer->setAutoReconnectPause((float) $this->option('auto-reconnect-pause'));
        $consumer->setAliveCheck((float) $this->option('alive-check'));

        $consumer->setInteractWithIO($this);
        $this->useVerboseForMessages = $this->booleanOption('verbose-messages');

        parent::handle();
    }

    protected function consumerTag(): string
    {
        if ($consumerTag = $this->option('consumer-tag')) {
            return $consumerTag;
        }

        $consumerTag = implode('_', [
            Str::slug(config('app.name', 'laravel')),
            Str::slug($this->option('name')),
            md5(serialize($this->options()).Str::random(16).getmypid()),
        ]);

        return Str::substr($consumerTag, 0, 255);
    }

    protected function booleanOption(string $key): bool
    {
        return filter_var(
            $this->option($key),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Output worker results only in verbose mode
     */
    protected function listenForEvents()
    {
        if ($this->useVerboseForMessages) {
            parent::listenForEvents();

            return;
        }

        $this->laravel['events']->listen(JobFailed::class, function ($event) {
            if ($this->output->isVerbose()) {
                $this->writeOutput($event->job, 'failed');
            }

            $this->logFailedJob($event);
        });

        if ($this->output->isVerbose()) {
            $this->laravel['events']->listen(JobProcessing::class, function ($event) {
                $this->writeOutput($event->job, 'starting');
            });

            $this->laravel['events']->listen(JobProcessed::class, function ($event) {
                $this->writeOutput($event->job, 'success');
            });

            $this->laravel['events']->listen(JobReleasedAfterException::class, function ($event) {
                $this->writeOutput($event->job, 'released_after_exception');
            });
        }
    }
}
