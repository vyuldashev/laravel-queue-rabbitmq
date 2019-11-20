<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Interop\Queue\Exception\PurgeQueueNotSupportedException;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @throws PurgeQueueNotSupportedException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->connection()->declareEverything('default');

        $this->connection()->getContext()->purgeQueue(
            $this->connection()->getContext()->createQueue('default')
        );
    }

    public function testPush(): void
    {
        $this->assertSame(0, Queue::size());

        Queue::pushRaw($payload = Str::random());

        $this->assertSame(1, Queue::size());

        $this->assertNotNull($job = Queue::pop());

        $this->assertInstanceOf(RabbitMQJob::class, $job);
        $this->assertSame($payload, $job->getRawBody());

        $job->delete();

        $this->assertSame(0, Queue::size());
    }

    public function testLater(): void
    {
        $this->assertSame(0, Queue::size());

        $payload = Str::random();
        $data = [Str::random() => Str::random()];

        Queue::later(3, $payload, $data);

        sleep(1);

        $this->assertSame(0, Queue::size());
        $this->assertNull(Queue::pop());

        sleep(3);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());

        $this->assertInstanceOf(RabbitMQJob::class, $job);
        $this->assertSame($payload, $job->getName());

        $body = json_decode($job->getRawBody(), true);

        $this->assertSame($payload, $body['displayName']);
        $this->assertSame($payload, $body['job']);
        $this->assertSame($data, $body['data']);

        $job->delete();

        $this->assertSame(0, Queue::size());
    }

    public function testJobAttempts(): void
    {
        $expectedPayload = __METHOD__.microtime(true);

        Queue::pushRaw($expectedPayload);

        $job = Queue::pop();
        $this->assertSame(1, $job->attempts());

        $job->release();

        $job = Queue::pop();

        $this->assertInstanceOf(RabbitMQJob::class, $job);
        $this->assertSame(2, $job->attempts());
    }
}
