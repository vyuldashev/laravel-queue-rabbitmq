<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Feature;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerStopReason;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
use RuntimeException;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks\TestJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks\TestTimeoutJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\TestCase;

class ConsumeCommandTest extends TestCase
{
    private string $queue;

    protected function setUp(): void
    {
        parent::setUp();

        // The connector closes the connection when the worker stops, so the queue
        // cannot be deleted in tearDown(). RabbitMQ removes an auto-delete queue
        // itself once the consumer is gone.
        $this->queue = 'consume-'.Str::random(8);

        $this->connection()->declareQueue($this->queue, false, true);
    }

    protected function tearDown(): void
    {
        // The worker installs its signal handlers in the PHPUnit process. Restore the
        // defaults so that Ctrl+C still stops the rest of the test run.
        if (method_exists(Worker::class, 'killUsing')) {
            Worker::killUsing(null);
        }

        if (extension_loaded('pcntl')) {
            pcntl_alarm(0);

            foreach ([SIGINT, SIGTERM, SIGQUIT, SIGUSR2, SIGCONT, SIGALRM] as $signal) {
                pcntl_signal($signal, SIG_DFL);
            }
        }

        parent::tearDown();
    }

    public function testConsumeStopsAfterMaxJobs(): void
    {
        Queue::push(new TestJob, '', $this->queue);

        [$processed, $stopping] = $this->consume(['--max-jobs' => 1]);

        $this->assertSame(1, $processed);
        $this->assertSame(0, $stopping->status);

        if (enum_exists(WorkerStopReason::class)) {
            $this->assertSame(WorkerStopReason::MaxJobsExceeded, $stopping->reason);
        }

        // Laravel 13.18+ also reports the worker counters on the event.
        if (property_exists($stopping, 'jobsProcessed')) {
            $this->assertSame(1, $stopping->jobsProcessed);
            $this->assertNotNull($stopping->lastJobProcessedAt);
        }
    }

    public function testConsumeStopsWhenQueueIsEmpty(): void
    {
        [$processed, $stopping] = $this->consume(['--stop-when-empty' => true]);

        $this->assertSame(0, $processed);
        $this->assertSame(0, $stopping->status);

        if (enum_exists(WorkerStopReason::class)) {
            $this->assertSame(WorkerStopReason::QueueEmpty, $stopping->reason);
        }
    }

    public function testConsumeReportsTheRunningJobWhenItTimesOut(): void
    {
        if (! extension_loaded('pcntl') || ! property_exists(WorkerStopping::class, 'jobsProcessed')) {
            $this->markTestSkipped('Laravel 13.18+ reports the worker counters when a timed out job kills the worker.');
        }

        // Worker::kill() would end the PHPUnit process: stop at the callback instead.
        Worker::killUsing(function (): void {
            throw new RuntimeException('The worker was killed.');
        });

        $stopping = null;

        Event::listen(WorkerStopping::class, function (WorkerStopping $event) use (&$stopping): void {
            $stopping ??= $event;
        });

        Queue::push(new TestTimeoutJob, '', $this->queue);

        try {
            $this->artisan('rabbitmq:consume', [
                '--queue' => $this->queue,
                '--sleep' => 0,
                '--max-time' => 10,
                '--tries' => 0,
            ])->run();
        } catch (AMQPChannelClosedException) {
            // The killed worker keeps running here, until its channel is closed.
        }

        $this->assertInstanceOf(WorkerStopping::class, $stopping);
        $this->assertSame(1, $stopping->status);
        $this->assertSame(1, $stopping->jobsProcessed);
    }

    public function testConsumeInMaintenanceModeStillStopsOnMaxTime(): void
    {
        // A paused consumer that never checks whether to stop would block the test run:
        // fail instead.
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGALRM, function (): void {
                throw new RuntimeException('rabbitmq:consume did not stop while the application was down');
            });
            pcntl_alarm(30);
        }

        $this->app->maintenanceMode()->activate([]);

        try {
            [$processed, $stopping] = $this->consume([]);
        } finally {
            $this->app->maintenanceMode()->deactivate();
        }

        $this->assertSame(0, $processed);
        $this->assertSame(0, $stopping->status);

        if (enum_exists(WorkerStopReason::class)) {
            $this->assertSame(WorkerStopReason::MaxTimeExceeded, $stopping->reason);
        }
    }

    /**
     * Run rabbitmq:consume on the test queue and return the number of processed jobs
     * and the WorkerStopping event. --max-time stops the consumer if the stop
     * condition under test is never met, so a regression fails instead of hanging.
     *
     * @return array{0: int, 1: WorkerStopping}
     */
    private function consume(array $options): array
    {
        $processed = 0;
        $stopping = null;

        Event::listen(JobProcessed::class, function () use (&$processed): void {
            $processed++;
        });

        Event::listen(WorkerStopping::class, function (WorkerStopping $event) use (&$stopping): void {
            $stopping = $event;
        });

        $this->artisan('rabbitmq:consume', [
            '--queue' => $this->queue,
            '--sleep' => 0,
            '--max-time' => 10,
        ] + $options)->run();

        $this->assertInstanceOf(WorkerStopping::class, $stopping);

        return [$processed, $stopping];
    }
}
