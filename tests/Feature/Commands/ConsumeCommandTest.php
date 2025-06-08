<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Feature\Commands;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Wire\IO\StreamIO;
use PHPUnit\Framework\Attributes\TestWith;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Functional\TestCase;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks\TestJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks\TestJobCallService;

class ConsumeCommandTest extends TestCase
{
    #[TestWith([false])]
    #[TestWith([true])]
    public function test_consume_one_job(bool $blocking): void
    {
        $queueNameAndConnection = Str::random();
        $this->app['config']->set("queue.connections.$queueNameAndConnection", $this->app['config']->get('queue.connections.rabbitmq'));
        $this->app['config']->set("queue.connections.$queueNameAndConnection.queue", $queueNameAndConnection);

        $service = $this->createMock(TestJob::class);
        $serviceName = Str::random();
        $this->app->singleton($serviceName, fn() => $service);
        $service
            ->expects($this->once())
            ->method('handle');

        Queue::push(new TestJobCallService($serviceName, 'handle'), '', $queueNameAndConnection);
        $blocking = (int)$blocking;
        $this->artisan("rabbitmq:consume $queueNameAndConnection --max-jobs=1 --blocking=$blocking");
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_consume_with_retry(bool $blocking): void
    {
        $queueNameAndConnection = Str::random();
        $this->app['config']->set("queue.connections.$queueNameAndConnection", $this->app['config']->get('queue.connections.rabbitmq'));
        $this->app['config']->set("queue.connections.$queueNameAndConnection.queue", $queueNameAndConnection);

        /** @var RabbitMQQueue $connection */
        $connection = Queue::connection($queueNameAndConnection);

        // First job does nothing
        $service1 = $this->createMock(TestJob::class);
        $serviceName1 = Str::random();
        $this->app->singleton($serviceName1, fn() => $service1);
        $service1
            ->expects($this->once())
            ->method('handle');
        Queue::push(new TestJobCallService($serviceName1, 'handle'), '', $queueNameAndConnection);

        // Second job must fail first time (by breaking channel) and be success second time
        $service2 = (object)[];
        $numberOfCalls = 0;
        $service2->callback = function () use (&$numberOfCalls, $connection) {
            $numberOfCalls++;
            if ($numberOfCalls === 1) {
                $connection->getChannel()->close();
            }
        };
        $serviceName2 = Str::random();
        $this->app->singleton($serviceName2, fn() => $service2);

        Queue::push(new TestJobCallService($serviceName2, 'callback'), '', $queueNameAndConnection);

        $blocking = (int)$blocking;
        $this->artisan("rabbitmq:consume $queueNameAndConnection --max-jobs=2 --blocking=$blocking --auto-reconnect=1 --verbose-messages=1 --init-queue=1");
        $this->assertEquals(2, $numberOfCalls);
    }

    public function test_consume_blocking_alive_check(): void
    {
        $this->markTestSkippedUnless(extension_loaded('sockets'), 'Sockets extension is required');

        $handler = $this->createMock(ExceptionHandler::class);
        $handler
            ->expects($this->once())
            ->method('report')
            ->willReturnCallback(function (AMQPTimeoutException $exception) {
                $this->assertEquals('Custom alive check failed', $exception->getMessage());
            });
        $this->app->instance(ExceptionHandler::class, $handler);
        $queueNameAndConnection = Str::random();
        $this->app['config']->set("queue.connections.$queueNameAndConnection", $this->app['config']->get('queue.connections.rabbitmq'));
        $this->app['config']->set("queue.connections.$queueNameAndConnection.queue", $queueNameAndConnection);
        $this->app['config']->set("queue.connections.$queueNameAndConnection.options.channel_rpc_timeout", 1);
        $this->app['config']->set("queue.connections.$queueNameAndConnection.options.keepalive", true);

        /** @var RabbitMQQueue $connection */
        $connection = Queue::connection($queueNameAndConnection);

        $port = random_int(10000, 50000);
        $pathToSocksFile = __DIR__ . '/../../Script/socket_fake.php';
        $descriptors = [
            ['pipe', 'r'], // stdin
            ['pipe', 'w'], // stdout
            ['pipe', 'w'], // stderr
        ];
        $proc = proc_open("php $pathToSocksFile $port", $descriptors, $pipes);
        sleep(1);
        $status = proc_get_status($proc);
        if (!$status['running']) {
            throw new \Exception('Cant make fake socket' . fread($pipes[1], 8192));
        }

        try {
            $service = (object)[];
            $numberOfCalls1 = 0;
            // Listen and do nothing
            $service->callback1 = function () use (&$numberOfCalls1, $connection, $port) {
                $numberOfCalls1++;

                // Emulate problems with connection
                $input = $this->callProperty($connection->getConnection(), 'input');
                $className = get_class($input);
                $reflection = new \ReflectionClass($className);

                $newStream = new StreamIO('127.0.0.1', $port, 1, 1);
                $newStream->connect();

                $property = $reflection->getProperty('io');
                $property->setAccessible(true);
                $property->setValue($input, $newStream);
            };
            $numberOfCalls2 = 0;
            $service->callback2 = function () use (&$numberOfCalls2) {
                // do nothing
                $numberOfCalls2++;
            };
            $serviceName = Str::random();
            $this->app->singleton($serviceName, fn() => $service);
            $connection->push(new TestJobCallService($serviceName, 'callback1'), '', $queueNameAndConnection);
            $connection->push(new TestJobCallService($serviceName, 'callback2'), '', $queueNameAndConnection);

            $this->artisan("rabbitmq:consume $queueNameAndConnection --max-jobs=2 --blocking=1 --auto-reconnect=1 --alive-check=1 --init-queue=1");
            $this->assertEquals(1, $numberOfCalls1);
            $this->assertEquals(1, $numberOfCalls2);
            $this->assertEquals(0, $connection->size($queueNameAndConnection));
        } finally {
            proc_terminate($proc);
            proc_close($proc);
        }
    }
}
