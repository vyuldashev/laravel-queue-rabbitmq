<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;
use VladimirYuldashev\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

class LaravelQueueRabbitMQServiceProviderTest extends TestCase
{
    public function testShouldSubClassServiceProviderClass()
    {
        $rc = new \ReflectionClass(LaravelQueueRabbitMQServiceProvider::class);

        $this->assertTrue($rc->isSubclassOf(ServiceProvider::class));
    }

    public function testShouldMergeQueueConfigOnRegister()
    {
        $dir = realpath(__DIR__.'/../src');

        //guard
        $this->assertDirectoryExists($dir);

        $providerMock = $this->createPartialMock(LaravelQueueRabbitMQServiceProvider::class, ['mergeConfigFrom']);

        $providerMock
            ->expects($this->once())
            ->method('mergeConfigFrom')
            ->with($dir.'/../config/rabbitmq.php', 'queue.connections.rabbitmq')
        ;

        $providerMock->register();
    }

    public function testShouldAddRabbitMQConnectorOnBoot()
    {
        $dispatcherMock = $this->createMock(Dispatcher::class);

        $queueMock = $this->createMock(QueueManager::class);
        $queueMock
            ->expects($this->once())
            ->method('addConnector')
            ->with('rabbitmq', $this->isInstanceOf(\Closure::class))
            ->willReturnCallback(function ($driver, \Closure $resolver) use ($dispatcherMock) {
                $connector = $resolver();

                $this->assertInstanceOf(RabbitMQConnector::class, $connector);
                $this->assertAttributeSame($dispatcherMock, 'dispatcher', $connector);
            })
        ;

        $app = Container::getInstance();
        $app['queue'] = $queueMock;
        $app['events'] = $dispatcherMock;

        $providerMock = new LaravelQueueRabbitMQServiceProvider($app);

        $providerMock->boot();
    }
}
