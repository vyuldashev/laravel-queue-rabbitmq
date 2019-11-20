<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Unit\Queue\Connectors;

use Closure;
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Queue\Events\WorkerStopping;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpTopic;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use stdClass;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks\AmqpConnectionFactorySpy;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks\CustomContextAmqpConnectionFactoryMock;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks\DelayStrategyAwareAmqpConnectionFactorySpy;

class RabbitMQConnectorTest extends TestCase
{
    public function testShouldImplementConnectorInterface(): void
    {
        $rc = new ReflectionClass(RabbitMQConnector::class);

        $this->assertTrue($rc->implementsInterface(ConnectorInterface::class));
    }

    public function testCouldBeConstructedWithDispatcherAsFirstArgument(): void
    {
        new RabbitMQConnector($this->createMock(Dispatcher::class));
    }

    /**
     * @throws ReflectionException
     */
    public function testThrowsIfFactoryClassIsNotValidClass(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The factory_class option has to be valid class that implements "Interop\Amqp\AmqpConnectionFactory"');

        $connector = new RabbitMQConnector($this->createMock(Dispatcher::class));

        $connector->connect(['factory_class' => 'invalidClassName']);
    }

    /**
     * @throws ReflectionException
     */
    public function testThrowsIfFactoryClassDoesNotImplementConnectorFactoryInterface(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The factory_class option has to be valid class that implements "Interop\Amqp\AmqpConnectionFactory"');

        $connector = new RabbitMQConnector($this->createMock(Dispatcher::class));

        $connector->connect(['factory_class' => stdClass::class]);
    }

    /**
     * @throws ReflectionException
     */
    public function testShouldPassExpectedConfigToConnectionFactory(): void
    {
        $called = false;

        AmqpConnectionFactorySpy::$spy = function ($config) use (&$called): void {
            $called = true;

            $this->assertEquals([
                'dsn' => 'theDsn',
                'host' => 'theHost',
                'port' => 'thePort',
                'user' => 'theLogin',
                'pass' => 'thePassword',
                'vhost' => 'theVhost',
                'ssl_on' => 'theSslOn',
                'ssl_verify' => 'theVerifyPeer',
                'ssl_cacert' => 'theCafile',
                'ssl_cert' => 'theLocalCert',
                'ssl_key' => 'theLocalKey',
                'ssl_passphrase' => 'thePassPhrase',
            ], $config);
        };

        $connector = new RabbitMQConnector($this->createMock(Dispatcher::class));

        $config = $this->createDummyConfig();
        $config['factory_class'] = AmqpConnectionFactorySpy::class;

        $connector->connect($config);

        $this->assertTrue($called);
    }

    /**
     * @throws ReflectionException
     */
    public function testShouldReturnExpectedInstanceOfQueueOnConnect(): void
    {
        $connector = new RabbitMQConnector($this->createMock(Dispatcher::class));

        $config = $this->createDummyConfig();
        $config['factory_class'] = AmqpConnectionFactorySpy::class;

        $queue = $connector->connect($config);

        $this->assertInstanceOf(RabbitMQQueue::class, $queue);
    }

    /**
     * @throws ReflectionException
     */
    public function testShouldSetRabbitMqDlxDelayStrategyIfConnectionFactoryImplementsDelayStrategyAwareInterface(): void
    {
        $connector = new RabbitMQConnector($this->createMock(Dispatcher::class));

        $called = false;
        DelayStrategyAwareAmqpConnectionFactorySpy::$spy = function ($actualStrategy) use (&$called): void {
            $this->assertInstanceOf(RabbitMqDlxDelayStrategy::class, $actualStrategy);

            $called = true;
        };

        $config = $this->createDummyConfig();
        $config['factory_class'] = DelayStrategyAwareAmqpConnectionFactorySpy::class;

        $connector->connect($config);

        $this->assertTrue($called);
    }

    /**
     * @throws ReflectionException
     */
    public function testShouldCallContextCloseMethodOnWorkerStoppingEvent(): void
    {
        $contextMock = $this->createMock(AmqpContext::class);
        $contextMock
            ->expects($this->once())
            ->method('close');

        $dispatcherMock = $this->createMock(Dispatcher::class);
        $dispatcherMock
            ->expects($this->once())
            ->method('listen')
            ->with(WorkerStopping::class, $this->isInstanceOf(Closure::class))
            ->willReturnCallback(static function ($eventName, Closure $listener): void {
                $listener();
            });

        CustomContextAmqpConnectionFactoryMock::$context = $contextMock;

        $connector = new RabbitMQConnector($dispatcherMock);

        $config = $this->createDummyConfig();
        $config['factory_class'] = CustomContextAmqpConnectionFactoryMock::class;

        $connector->connect($config);
    }

    /**
     * @return array
     */
    private function createDummyConfig(): array
    {
        return [
            'dsn' => 'theDsn',
            'host' => 'theHost',
            'port' => 'thePort',
            'login' => 'theLogin',
            'password' => 'thePassword',
            'vhost' => 'theVhost',
            'ssl_params' => [
                'ssl_on' => 'theSslOn',
                'verify_peer' => 'theVerifyPeer',
                'cafile' => 'theCafile',
                'local_cert' => 'theLocalCert',
                'local_key' => 'theLocalKey',
                'passphrase' => 'thePassPhrase',
            ],
            'options' => [
                'exchange' => [
                    'name' => 'anExchangeName',
                    'declare' => false,
                    'type' => AmqpTopic::TYPE_DIRECT,
                    'passive' => false,
                    'durable' => true,
                    'auto_delete' => false,
                ],

                'queue' => [
                    'name' => 'aQueueName',
                    'declare' => false,
                    'bind' => false,
                    'passive' => false,
                    'durable' => true,
                    'exclusive' => false,
                    'auto_delete' => false,
                    'arguments' => '[]',
                ],
            ],
            'sleep_on_error' => getenv('RABBITMQ_ERROR_SLEEP', 5),
        ];
    }
}
