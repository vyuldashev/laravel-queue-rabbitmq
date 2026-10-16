<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Functional;

use Mockery;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\TestCase as BaseTestCase;

class RabbitMQJobTest extends BaseTestCase
{
    public function testAttemptsWithoutHeaders(): void
    {
        $this->assertSame(1, $this->job()->attempts());
    }

    public function testAttemptsCountsReleases(): void
    {
        $this->assertSame(3, $this->job(['laravel' => ['attempts' => 2]])->attempts());
    }

    public function testAttemptsCountsBrokerRedeliveries(): void
    {
        $this->assertSame(3, $this->job(['x-delivery-count' => 2])->attempts());
    }

    public function testAttemptsCountsReleasesAndBrokerRedeliveries(): void
    {
        $this->assertSame(4, $this->job(['laravel' => ['attempts' => 1], 'x-delivery-count' => 2])->attempts());
    }

    public function testReleasePublishesNewMessageWithAllAttempts(): void
    {
        $queue = Mockery::mock(RabbitMQQueue::class);
        $job = $this->job(['laravel' => ['attempts' => 1], 'x-delivery-count' => 2], $queue);

        $queue->shouldReceive('laterRaw')->once()->with(0, $job->getRawBody(), 'default', 4);
        $queue->shouldReceive('ack')->once()->with($job);

        $job->release();

        $this->assertTrue($job->isReleased());
    }

    protected function job(?array $headers = null, ?RabbitMQQueue $queue = null): RabbitMQJob
    {
        $properties = $headers === null ? [] : ['application_headers' => new AMQPTable($headers)];

        return new RabbitMQJob(
            $this->app,
            $queue ?? Mockery::mock(RabbitMQQueue::class),
            new AMQPMessage(json_encode(['id' => 'foo', 'job' => 'bar']), $properties),
            'rabbitmq',
            'default'
        );
    }
}
