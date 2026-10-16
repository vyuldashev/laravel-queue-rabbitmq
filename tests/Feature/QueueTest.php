<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Feature;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks\TestJob;

class QueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutExceptionHandling([
            AMQPChannelClosedException::class, AMQPConnectionClosedException::class,
            AMQPProtocolChannelException::class,
        ]);
    }

    public function testConnection(): void
    {
        $this->assertInstanceOf(AMQPStreamConnection::class, $this->connection()->getChannel()->getConnection());
    }

    public function testWithoutReconnect(): void
    {
        $queue = $this->connection('rabbitmq');

        $queue->push(new TestJob);
        sleep(1);
        $this->assertSame(1, $queue->size());

        // close connection
        $queue->getConnection()->close();
        $this->assertFalse($queue->getConnection()->isConnected());

        $this->expectException(AMQPChannelClosedException::class);
        $queue->push(new TestJob);
    }

    public function testAttemptsCountBrokerRedeliveriesOnQuorumQueue(): void
    {
        $this->app['config']->set('queue.connections.rabbitmq-quorum', array_merge(
            $this->app['config']->get('queue.connections.rabbitmq'),
            ['options' => ['queue' => ['quorum' => true]]]
        ));
        $queue = $this->connection('rabbitmq-quorum');
        $queue->deleteQueue('quorum-redelivery');

        $queue->push(new TestJob, '', 'quorum-redelivery');

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->assertInstanceOf(RabbitMQJob::class, $job = $this->popWithin($queue, 'quorum-redelivery'));
            $this->assertSame($attempt, $job->attempts());

            // Simulate a worker that dies mid-job: the broker redelivers the unacked message.
            $queue->getChannel()->close();
            $queue->getChannel(true);
        }

        $this->assertInstanceOf(RabbitMQJob::class, $job = $this->popWithin($queue, 'quorum-redelivery'));
        $job->delete();
        $queue->deleteQueue('quorum-redelivery');
    }
}
