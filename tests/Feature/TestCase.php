<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Feature;

use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks\TestEncryptedJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks\TestJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Set to false for skipped tests.
     */
    protected bool $interactsWithConnection = true;

    /**
     * @throws AMQPProtocolChannelException
     */
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->interactsWithConnection) {
            if ($this->connection()->isQueueExists()) {
                $this->connection()->purge();
            }
        }
    }

    /**
     * @throws AMQPProtocolChannelException
     */
    protected function tearDown(): void
    {
        if ($this->interactsWithConnection) {
            if ($this->connection()->isQueueExists()) {
                $this->connection()->purge();
            }

            self::assertSame(0, Queue::size());
        }

        parent::tearDown();
    }

    public function testSizeDoesNotThrowExceptionOnUnknownQueue(): void
    {
        $this->assertEmpty(0, Queue::size(Str::random()));
    }

    public function testPopNothing(): void
    {
        $this->assertNull(Queue::pop('foo'));
    }

    public function testPushRaw(): void
    {
        Queue::pushRaw($payload = Str::random());

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(1, $job->attempts());
        $this->assertInstanceOf(RabbitMQJob::class, $job);
        $this->assertSame($payload, $job->getRawBody());

        $this->assertNull($job->getJobId());

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testPushBulkRaw(): void
    {
        $payload1 = Str::random();
        $payload2 = Str::random();

        Queue::pushBulkRaw([$payload1, $payload2]);

        sleep(1);

        $this->assertSame(2, Queue::size());
        $this->assertNotNull($job1 = Queue::pop());
        $this->assertNotNull($job2 = Queue::pop());
        $this->assertSame(1, $job1->attempts());
        $this->assertSame(1, $job2->attempts());
        $this->assertInstanceOf(RabbitMQJob::class, $job1);
        $this->assertInstanceOf(RabbitMQJob::class, $job2);
        $this->assertSame($payload1, $job1->getRawBody());
        $this->assertSame($payload2, $job2->getRawBody());

        $this->assertNull($job1->getJobId());
        $this->assertNull($job2->getJobId());

        $job1->delete();
        $job2->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testPush(): void
    {
        Queue::push(new TestJob);

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(1, $job->attempts());
        $this->assertInstanceOf(RabbitMQJob::class, $job);
        $this->assertSame(TestJob::class, $job->resolveName());
        $this->assertNotNull($job->getJobId());

        $payload = $job->payload();

        $this->assertSame(TestJob::class, $payload['displayName']);
        $this->assertSame('Illuminate\Queue\CallQueuedHandler@call', $payload['job']);
        $this->assertNull($payload['maxTries']);
        $this->assertNull($payload['backoff']);
        $this->assertNull($payload['timeout']);
        $this->assertNull($payload['retryUntil']);
        $this->assertSame($job->getJobId(), $payload['id']);

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testPushAfterCommit(): void
    {
        $transaction = new DatabaseTransactionsManager;

        $this->app->singleton('db.transactions', function ($app) use ($transaction) {
            $transaction->begin('FakeDBConnection', 1);

            return $transaction;
        });

        TestJob::dispatch()->afterCommit();

        sleep(1);
        $this->assertSame(0, Queue::size());
        $this->assertNull(Queue::pop());

        $transaction->commit('FakeDBConnection', 1, 0);

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testLaterRaw(): void
    {
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

    #[TestWith([false])]
    #[TestWith([true])]
    public function testLater(bool $useExpirationOnMessage): void
    {
        $queueName = Str::random();
        // Make another connection
        $this->app['config']->set('queue.connections.rabbitmq2', $this->app['config']->get('queue.connections.rabbitmq'));
        $this->app['config']->set('queue.connections.rabbitmq2.queue', $queueName);
        $this->app['config']->set('queue.connections.rabbitmq2.options.queue.use_expiration_for_delayed_queues', $useExpirationOnMessage);
        // Disable caching
        $this->app['config']->set('queue.connections.rabbitmq2.options.queue.cache_declared', false);

        if ($useExpirationOnMessage) {
            $laterQueueName = "{$queueName}_deferred";
        } else {
            $laterQueueName = "{$queueName}.delay.3000";
        }

        /** @var RabbitMQQueue $connection */
        $connection = Queue::connection('rabbitmq2');
        $this->assertFalse($connection->isQueueExists($laterQueueName));
        $connection->later(3, new TestJob);
        $this->assertTrue($connection->isQueueExists($laterQueueName));
        sleep(1);

        $this->assertSame(0, $connection->size());
        $this->assertNull($connection->pop());

        sleep(3);

        $this->assertSame(1, $connection->size());
        $this->assertNotNull($job = $connection->pop());

        $this->assertInstanceOf(RabbitMQJob::class, $job);

        $body = json_decode($job->getRawBody(), true);

        $this->assertSame(TestJob::class, $body['displayName']);
        $this->assertSame('Illuminate\Queue\CallQueuedHandler@call', $body['job']);
        $this->assertSame(TestJob::class, $body['data']['commandName']);
        $this->assertNotNull($job->getJobId());

        $job->delete();
        $this->assertSame(0, $connection->size());
    }

    public function testBulk(): void
    {
        $count = 100;
        $jobs = [];

        for ($i = 0; $i < $count; $i++) {
            $jobs[$i] = new TestJob($i);
        }

        Queue::bulk($jobs);

        sleep(1);

        $this->assertSame($count, Queue::size());
    }

    public function testPushEncrypted(): void
    {
        Queue::push(new TestEncryptedJob);

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(1, $job->attempts());
        $this->assertInstanceOf(RabbitMQJob::class, $job);
        $this->assertSame(TestEncryptedJob::class, $job->resolveName());
        $this->assertNotNull($job->getJobId());

        $payload = $job->payload();

        $this->assertSame(TestEncryptedJob::class, $payload['displayName']);
        $this->assertSame('Illuminate\Queue\CallQueuedHandler@call', $payload['job']);
        $this->assertNull($payload['maxTries']);
        $this->assertNull($payload['backoff']);
        $this->assertNull($payload['timeout']);
        $this->assertNull($payload['retryUntil']);
        $this->assertSame($job->getJobId(), $payload['id']);

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testPushEncryptedAfterCommit(): void
    {
        $transaction = new DatabaseTransactionsManager;

        $this->app->singleton('db.transactions', function ($app) use ($transaction) {
            $transaction->begin('FakeDBConnection', 1);

            return $transaction;
        });

        TestEncryptedJob::dispatch()->afterCommit();

        sleep(1);
        $this->assertSame(0, Queue::size());
        $this->assertNull(Queue::pop());

        $transaction->commit('FakeDBConnection', 1, 0);

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testEncryptedLater(): void
    {
        Queue::later(3, new TestEncryptedJob);

        sleep(1);

        $this->assertSame(0, Queue::size());
        $this->assertNull(Queue::pop());

        sleep(3);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());

        $this->assertInstanceOf(RabbitMQJob::class, $job);

        $body = json_decode($job->getRawBody(), true);

        $this->assertSame(TestEncryptedJob::class, $body['displayName']);
        $this->assertSame('Illuminate\Queue\CallQueuedHandler@call', $body['job']);
        $this->assertSame(TestEncryptedJob::class, $body['data']['commandName']);
        $this->assertNotNull($job->getJobId());

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testEncryptedBulk(): void
    {
        $count = 100;
        $jobs = [];

        for ($i = 0; $i < $count; $i++) {
            $jobs[$i] = new TestEncryptedJob($i);
        }

        Queue::bulk($jobs);

        sleep(1);

        $this->assertSame($count, Queue::size());
    }

    public function testReleaseRaw(): void
    {
        Queue::pushRaw($payload = Str::random());

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(1, $job->attempts());

        for ($attempt = 2; $attempt <= 4; $attempt++) {
            $job->release();

            sleep(1);

            $this->assertSame(1, Queue::size());

            $job = Queue::pop();

            $this->assertSame($attempt, $job->attempts());
        }

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testRelease(): void
    {
        Queue::push(new TestJob);

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(1, $job->attempts());

        for ($attempt = 2; $attempt <= 4; $attempt++) {
            $job->release();

            sleep(1);

            $this->assertSame(1, Queue::size());

            $job = Queue::pop();

            $this->assertSame($attempt, $job->attempts());
        }

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testReleaseWithDelayRaw(): void
    {
        Queue::pushRaw($payload = Str::random());

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(1, $job->attempts());

        for ($attempt = 2; $attempt <= 4; $attempt++) {
            $job->release(4);

            sleep(1);

            $this->assertSame(0, Queue::size());
            $this->assertNull(Queue::pop());

            sleep(4);

            $this->assertSame(1, Queue::size());

            $job = Queue::pop();

            $this->assertSame($attempt, $job->attempts());
        }

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testReleaseInThePast(): void
    {
        Queue::push(new TestJob);

        $job = Queue::pop();
        $job->release(-3);

        sleep(1);

        $this->assertInstanceOf(RabbitMQJob::class, $job = Queue::pop());

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testReleaseAndReleaseWithDelayAttempts(): void
    {
        Queue::push(new TestJob);

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());

        $job->release();

        sleep(1);

        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(2, $job->attempts());

        $job->release(3);

        sleep(4);

        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(3, $job->attempts());

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testDelete(): void
    {
        Queue::push(new TestJob);

        $job = Queue::pop();

        $job->delete();

        sleep(1);

        $this->assertSame(0, Queue::size());
        $this->assertNull(Queue::pop());
    }

    public function testFailed(): void
    {
        Queue::push(new TestJob);

        $job = Queue::pop();

        $job->fail(new RuntimeException($job->resolveName().' has an exception.'));

        sleep(1);

        $this->assertSame(true, $job->hasFailed());
        $this->assertSame(true, $job->isDeleted());
        $this->assertSame(0, Queue::size());
        $this->assertNull(Queue::pop());
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testPushRetry(bool $enableRetries): void
    {
        // Make another connection
        $this->app['config']->set('queue.connections.rabbitmq2', $this->app['config']->get('queue.connections.rabbitmq'));
        $this->app['config']->set('queue.connections.rabbitmq2.options.queue.retries', [
            'enabled' => $enableRetries,
            'max' => 1,
            'pause_micro_seconds' => 1,
        ]);

        /** @var RabbitMQQueue $connection */
        $connection = Queue::connection('rabbitmq2');
        $connection->declareQueue('default');

        // Now let's close connection, it will trigger retry
        $connection->getChannel()->close();
        if ($enableRetries) {
            $connection->push(new TestJob);
            $this->assertSame(1, $connection->size());
        } else {
            // Push will trigger exception
            try {
                $connection->push(new TestJob);
            } catch (AMQPExceptionInterface $exception) {
                $this->assertSame('Channel connection is closed.', $exception->getMessage());
            }
        }
    }

    public function testFullRouteDeclare(): void
    {
        // Make another connection
        $this->app['config']->set('queue.connections.rabbitmq2', $this->app['config']->get('queue.connections.rabbitmq'));
        $this->app['config']->set('queue.connections.rabbitmq2.options.queue.declare_full_route', true);

        $queue = Str::random();
        $exchange = Str::random();

        /** @var RabbitMQQueue $connection */
        $connection = Queue::connection('rabbitmq2');
        $this->assertFalse($connection->isQueueExists($queue));
        $this->assertFalse($connection->isExchangeExists($exchange));

        $connection->pushRaw('data', $queue, [
            'exchange' => $exchange,
        ]);

        $this->assertTrue($connection->isQueueExists($queue));
        $this->assertTrue($connection->isExchangeExists($exchange));
        $this->assertSame(1, $connection->size($queue));
    }
}
