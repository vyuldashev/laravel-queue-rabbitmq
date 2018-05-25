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
    /**
     * @throws \ReflectionException
     */
    public function testShouldSubClassServiceProviderClass()
    {
        $rc = new \ReflectionClass(LaravelQueueRabbitMQServiceProvider::class);

        $this->assertTrue($rc->isSubclassOf(ServiceProvider::class));
    }

    /**
     * @throws \ReflectionException
     */
    public function testShouldMergeQueueConfigOnRegister()
    {
        $dir = realpath(__DIR__.'/../src');

        //guard
        $this->assertDirectoryExists($dir);

        $app = $this->createPartialMock(Container::class, ['runningInConsole']);
        $app->expects($this->once())
            ->method('runningInConsole')
            ->willReturn(false);

        /** @var \PHPUnit_Framework_MockObject_MockObject|LaravelQueueRabbitMQServiceProvider $providerMock */
        $providerMock =$this->getMockBuilder(LaravelQueueRabbitMQServiceProvider::class)
            ->setMethods(['mergeConfigFrom', 'addRabbitMQConnector'])
            ->setConstructorArgs([$app])
            ->getMock();

        $providerMock
            ->expects($this->once())
            ->method('mergeConfigFrom')
            ->with($dir.'/../config/rabbitmq.php', 'queue.connections.rabbitmq')
        ;

        $providerMock->boot();
    }

    /**
     * @throws \ReflectionException
     */
    public function testShouldAddRabbitMQConnectorOnBootWhenQueueIsResolved()
    {
        $dispatcherMock = $this->createMock(Dispatcher::class);

        $queueMock = $this->createMock(QueueManager::class);
        $queueMock
            ->expects($this->once())
            ->method('addConnector')
            ->with('rabbitmq', $this->isInstanceOf(\Closure::class))
            ->willReturnCallback(function ($driver, \Closure $resolver) use ($dispatcherMock) {
                $connector = $resolver();

                $this->assertEquals('rabbitmq', $driver);
                $this->assertInstanceOf(RabbitMQConnector::class, $connector);
                $this->assertAttributeSame($dispatcherMock, 'dispatcher', $connector);
            })
        ;

        $app = $this->createPartialMock(Container::class,
            ['runningInConsole', 'resolved', 'afterResolving']
        );
        $app->expects($this->once())
            ->method('runningInConsole')
            ->willReturn(false);

        $app->method('resolved')
            ->willReturn(true);

        $app->expects($this->never())
            ->method('afterResolving');
        /** @var \PHPUnit_Framework_MockObject_MockObject|LaravelQueueRabbitMQServiceProvider $providerMock */
        $providerMock =$this->getMockBuilder(LaravelQueueRabbitMQServiceProvider::class)
            ->setMethods(['mergeConfigFrom'])
            ->setConstructorArgs([$app])
            ->getMock();

        $providerMock->expects($this->once())->method('mergeConfigFrom');

        $app['queue'] = $queueMock;
        $app['events'] = $dispatcherMock;


        $providerMock->boot();
    }

    /**
     * @throws \ReflectionException
     */
    public function testShouldAddRabbitMQConnectorOnBootWhenQueueIsNotResolved()
    {
        $dispatcherMock = $this->createMock(Dispatcher::class);

        $queueMock = $this->createMock(QueueManager::class);
        $queueMock
            ->expects($this->once())
            ->method('addConnector')
            ->with('rabbitmq', $this->isInstanceOf(\Closure::class))
            ->willReturnCallback(function ($driver, \Closure $resolver) use ($dispatcherMock) {
                $connector = $resolver();

                $this->assertEquals('rabbitmq', $driver);
                $this->assertInstanceOf(RabbitMQConnector::class, $connector);
                $this->assertAttributeSame($dispatcherMock, 'dispatcher', $connector);
            })
        ;

        $app = $this->createPartialMock(Container::class,
            ['runningInConsole', 'resolved', 'afterResolving']
        );
        $app->expects($this->once())
            ->method('runningInConsole')
            ->willReturn(false);

        $app->method('resolved')
            ->willReturnCallback(function ($abstract) {
                return !($abstract === 'queue');
            });

        $app->expects($this->once())
            ->method('afterResolving')
            ->willReturnCallback(function ($abstract, $callback) use ($queueMock) {
                $this->assertEquals('queue', $abstract);
                $callback($queueMock);
            });

        /** @var \PHPUnit_Framework_MockObject_MockObject|LaravelQueueRabbitMQServiceProvider $providerMock */
        $providerMock =$this->getMockBuilder(LaravelQueueRabbitMQServiceProvider::class)
            ->setMethods(['mergeConfigFrom'])
            ->setConstructorArgs([$app])
            ->getMock();

        $providerMock->expects($this->once())->method('mergeConfigFrom');

        $app['queue'] = $queueMock;
        $app['events'] = $dispatcherMock;


        $providerMock->boot();
    }

    /**
     * @throws \ReflectionException
     */
    public function testNotPublishedConfigsWhenNotConsole()
    {
        $app = $this->createPartialMock(Container::class, ['runningInConsole']);

        $app->expects($this->once())
            ->method('runningInConsole')
            ->willReturn(false);
        /** @var \PHPUnit_Framework_MockObject_MockObject|LaravelQueueRabbitMQServiceProvider $provider */
        $provider = $this->getMockBuilder(LaravelQueueRabbitMQServiceProvider::class)
            ->setMethods(['mergeConfigFrom', 'publishes', 'addRabbitMQConnector'])
            ->setConstructorArgs([$app])
            ->getMock();

        $provider->expects($this->never())->method('publishes');

        $provider->boot();
    }

    /**
     * @throws \ReflectionException
     */
    public function testPublishesWhenInConsole()
    {
        $app = $this->createPartialMock(Container::class, ['runningInConsole', 'configPath']);

        $app->expects($this->once())
            ->method('runningInConsole')
            ->willReturn(true);

        $app->expects($this->once())
            ->method('configPath')
            ->with('rabbitmq.php');

        /** @var \PHPUnit_Framework_MockObject_MockObject|LaravelQueueRabbitMQServiceProvider $provider */
        $provider = $this->getMockBuilder(LaravelQueueRabbitMQServiceProvider::class)
            ->setMethods(['mergeConfigFrom', 'publishes', 'addRabbitMQConnector'])
            ->setConstructorArgs([$app])
            ->getMock();

        $provider->expects($this->once())->method('publishes');

        $provider->boot();
    }
}
