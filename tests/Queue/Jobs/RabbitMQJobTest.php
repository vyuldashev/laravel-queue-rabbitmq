<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Queue\Jobs;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Database\DetectsDeadlocks;
use Illuminate\Queue\Jobs\Job;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\Impl\AmqpMessage;
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
        new RabbitMQJob(
            new Container(),
            $this->createRabbitMQQueueMock(),
            $this->createConsumerMock(),
            new AmqpMessage()
        );
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
