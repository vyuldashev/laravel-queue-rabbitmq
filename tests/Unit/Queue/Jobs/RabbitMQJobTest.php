<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Unit\Queue\Jobs;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Database\DetectsLostConnections;
use Illuminate\Queue\Jobs\Job;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\Impl\AmqpMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQJobTest extends TestCase
{
    public function testShouldImplementQueueInterface(): void
    {
        $rc = new ReflectionClass(RabbitMQJob::class);

        $this->assertTrue($rc->implementsInterface(JobContract::class));
    }

    public function testShouldBeSubClassOfQueue(): void
    {
        $rc = new ReflectionClass(RabbitMQJob::class);

        $this->assertTrue($rc->isSubclassOf(Job::class));
    }

    public function testShouldUseDetectDeadlocksTrait(): void
    {
        $rc = new ReflectionClass(RabbitMQJob::class);

        $this->assertContains(DetectsLostConnections::class, $rc->getTraitNames());
    }

    public function testCouldBeConstructedWithExpectedArguments(): void
    {
        $queue = $this->createMock(AmqpQueue::class);
        $queue
            ->expects($this->once())
            ->method('getQueueName')
            ->willReturn('theQueueName');

        $consumerMock = $this->createConsumerMock();
        $consumerMock
            ->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue);

        $connectionMock = $this->createRabbitMQQueueMock();
        $connectionMock
            ->method('getConnectionName')
            ->willReturn('theConnectionName');

        $job = new RabbitMQJob(
            new Container(),
            $connectionMock,
            $consumerMock,
            new AmqpMessage()
        );

        $this->assertSame('theQueueName', $job->getQueue());
        $this->assertSame('theConnectionName', $job->getConnectionName());
    }

    /**
     * @return AmqpConsumer|MockObject|AmqpConsumer
     */
    private function createConsumerMock()
    {
        return $this->createMock(AmqpConsumer::class);
    }

    /**
     * @return MockObject|RabbitMQQueue|RabbitMQQueue
     */
    private function createRabbitMQQueueMock()
    {
        return $this->createMock(RabbitMQQueue::class);
    }
}
