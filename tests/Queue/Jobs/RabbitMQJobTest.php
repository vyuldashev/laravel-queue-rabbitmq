<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Queue\Jobs;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Database\DetectsDeadlocks;
use Illuminate\Queue\Jobs\Job;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\Impl\AmqpMessage;
use Interop\Amqp\Impl\AmqpQueue;
use PHPUnit\Framework\TestCase;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQJobTest extends TestCase
{
    public function testShouldImplementQueueInterface()
    {
        $rc = new \ReflectionClass(RabbitMQJob::class);

        $this->assertTrue($rc->implementsInterface(JobContract::class));
    }

    public function testShouldBeSubClassOfQueue()
    {
        $rc = new \ReflectionClass(RabbitMQJob::class);

        $this->assertTrue($rc->isSubclassOf(Job::class));
    }

    public function testShouldUseDetectDeadlocksTrait()
    {
        $rc = new \ReflectionClass(RabbitMQJob::class);

        $this->assertContains(DetectsDeadlocks::class, $rc->getTraitNames());
    }

    public function testCouldBeConstructedWithExpectedArguments()
    {
        $queue = $this->createMock(\Interop\Amqp\AmqpQueue::class);
        $queue
            ->expects($this->once())
            ->method('getQueueName')
            ->willReturn('theQueueName')
        ;

        $consumerMock = $this->createConsumerMock();
        $consumerMock
            ->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue)
        ;

        $connectionMock = $this->createRabbitMQQueueMock();
        $connectionMock
            ->expects($this->any())
            ->method('getConnectionName')
            ->willReturn('theConnectionName')
        ;
        
        $job = new RabbitMQJob(
            new Container(),
            $connectionMock,
            $consumerMock,
            new AmqpMessage()
        );

        $this->assertAttributeSame('theQueueName', 'queue', $job);
        $this->assertSame('theConnectionName', $job->getConnectionName());
    }

    /**
     * @return AmqpConsumer|\PHPUnit_Framework_MockObject_MockObject|AmqpConsumer
     */
    private function createConsumerMock()
    {
        return $this->createMock(AmqpConsumer::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|RabbitMQQueue|RabbitMQQueue
     */
    private function createRabbitMQQueueMock()
    {
        return $this->createMock(RabbitMQQueue::class);
    }
}
