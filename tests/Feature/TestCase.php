<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks\TestJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\TestCase as BaseTestCase;

// https://github.com/laravel/framework/blob/6.x/tests/Queue/RedisQueueIntegrationTest.php
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::size();

        $this->connection()->purge();
    }

    public function testPop(): void
    {
        $this->assertNull(Queue::pop('foo'));
    }

    public function testPushRaw(): void
    {
        $this->assertSame(0, Queue::size());

        Queue::pushRaw($payload = Str::random());

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(0, $job->attempts());
        $this->assertInstanceOf(RabbitMQJob::class, $job);
        $this->assertSame($payload, $job->getRawBody());

        $this->assertNull($job->getJobId());

        $job->delete();

        $this->assertSame(0, Queue::size());
    }

    public function testReleaseRaw(): void
    {
        Queue::pushRaw($payload = Str::random());

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(0, $job->attempts());

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $job->release();

            sleep(1); // TODO ???

            $this->assertSame(1, Queue::size());

            $job = Queue::pop();

            $this->assertSame($attempt, $job->attempts());
        }
    }

    public function testReleaseWithDelayRaw(): void
    {
        $this->assertSame(0, Queue::size());

        Queue::pushRaw($payload = Str::random());

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(0, $job->attempts());

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $job->release(4);

            sleep(1); // TODO ???

            $this->assertSame(0, Queue::size());
            $this->assertNull(Queue::pop());

            sleep(4);

            $this->assertSame(1, Queue::size());

            $job = Queue::pop();

            $this->assertSame($attempt, $job->attempts());
        }
    }

    public function testLaterRaw(): void
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

    public function testPush(): void
    {
        Queue::push(new TestJob());

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(0, $job->attempts());
        $this->assertInstanceOf(RabbitMQJob::class, $job);
        $this->assertSame(TestJob::class, $job->resolveName());
        $this->assertNotNull($job->getJobId());

        $payload = $job->payload();

        $this->assertSame(TestJob::class, $payload['displayName']);
        $this->assertSame('Illuminate\Queue\CallQueuedHandler@call', $payload['job']);
        $this->assertNull($payload['maxTries']);
        $this->assertNull($payload['delay']);
        $this->assertNull($payload['timeout']);
        $this->assertNull($payload['timeoutAt']);
        $this->assertSame($job->getJobId(), $payload['id']);
    }

    public function testRelease(): void
    {
        Queue::push(new TestJob());

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(0, $job->attempts());

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $job->release();

            sleep(1); // TODO ???

            $this->assertSame(1, Queue::size());

            $job = Queue::pop();

            $this->assertSame($attempt, $job->attempts());
        }
    }

    public function testLater(): void
    {
        $this->assertSame(0, Queue::size());

        Queue::later(3, new TestJob());

        sleep(1);

        $this->assertSame(0, Queue::size());
        $this->assertNull(Queue::pop());

        sleep(3);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());

        $this->assertInstanceOf(RabbitMQJob::class, $job);

        $body = json_decode($job->getRawBody(), true);

        $this->assertSame(TestJob::class, $body['displayName']);
        $this->assertSame('Illuminate\Queue\CallQueuedHandler@call', $body['job']);
        $this->assertSame(TestJob::class, $body['data']['commandName']);
        $this->assertNotNull($job->getJobId());

        $job->delete();

        $this->assertSame(0, Queue::size());
    }

    public function testReleaseAndReleaseWithDelayAttempts(): void
    {
        $this->assertSame(0, Queue::size());

        Queue::push(new TestJob());

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());

        $job->release();

        sleep(1);

        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(1, $job->attempts());

        $job->release(3);

        sleep(4);

        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(2, $job->attempts());
    }

//    public function testFoo()
//    {
//        /** @var RabbitMQJob $job */
//        Queue::pushRaw('foo');
//
//        $job = Queue::pop();
//
//        $this->assertNotNull($job);
//
//        $job->release();
//
//        sleep(1);
//
//        $job = Queue::pop();
//
//        $this->assertNotNull($job);
//
//        $this->connection()->getChannel()->basic_reject($job->getMessage()->getDeliveryTag(), false);
//        $this->connection()->getChannel()->wait_for_pending_acks_returns();
//
//        sleep(1);
//
//        $job = Queue::pop();
//
//        sleep(1);
//
//        $this->connection()->getChannel()->basic_nack($job->getMessage()->getDeliveryTag(), false, false);
//        $this->connection()->getChannel()->wait_for_pending_acks_returns();
//
//        sleep(1);
//
//        $job = Queue::pop();
//
//        /** @var AMQPTable|null $headers */
//        $headers = Arr::get($job->getMessage()->get_properties(), 'application_headers');
//
//        dd($headers->getNativeData());
//    }
}
