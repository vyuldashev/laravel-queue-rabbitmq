<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Queue\Connectors;

use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Queue\Events\WorkerStopping;
use Interop\Amqp\AmqpContext;
use PHPUnit\Framework\TestCase;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mock\AmqpConnectionFactorySpy;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mock\CustomContextAmqpConnectionFactoryMock;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mock\DelayStrategyAwareAmqpConnectionFactorySpy;

class RabbitMQConnectorTest extends TestCase
{
    public function testShouldImplementConnectorInterface()
    {
        $rc = new \ReflectionClass(RabbitMQConnector::class);

        $this->assertTrue($rc->implementsInterface(ConnectorInterface::class));
    }

    public function testCouldBeConstructedWithDispatcherAsFirstArgument()
    {
        new RabbitMQConnector($this->createMock(Dispatcher::class));
    }

    public function testThrowsIfFactoryClassIsMissing()
    {
        $connector = new RabbitMQConnector($this->createMock(Dispatcher::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The factory_class option is missing though it is required.');
        $connector->connect([]);
    }

    public function testThrowsIfFactoryClassIsNotValidClass()
    {
        $connector = new RabbitMQConnector($this->createMock(Dispatcher::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The factory_class option has to be valid class that implements "Interop\Amqp\AmqpConnectionFactory"');
        $connector->connect(['factory_class' => 'invalidClassName']);
    }

    public function testThrowsIfFactoryClassDoesNotImplementConnectorFactoryInterface()
    {
        $connector = new RabbitMQConnector($this->createMock(Dispatcher::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The factory_class option has to be valid class that implements "Interop\Amqp\AmqpConnectionFactory"');
        $connector->connect(['factory_class' => \stdClass::class]);
    }

    public function testShouldPassExpectedConfigToConnectionFactory()
    {
        $called = false;
        AmqpConnectionFactorySpy::$spy = function ($config) use (&$called) {
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

    public function testShouldReturnExpectedInstanceOfQueueOnConnect()
    {
        $connector = new RabbitMQConnector($this->createMock(Dispatcher::class));

        $config = $this->createDummyConfig();
        $config['factory_class'] = AmqpConnectionFactorySpy::class;

        $queue = $connector->connect($config);

        $this->assertInstanceOf(RabbitMQQueue::class, $queue);
    }

    public function testShouldSetRabbitMqDlxDelayStrategyIfConnectionFactoryImplementsDelayStrategyAwareInterface()
    {
        $connector = new RabbitMQConnector($this->createMock(Dispatcher::class));

        $called = false;
        DelayStrategyAwareAmqpConnectionFactorySpy::$spy = function ($actualStrategy) use (&$called) {
            $this->assertInstanceOf(RabbitMqDlxDelayStrategy::class, $actualStrategy);

            $called = true;
        };

        $config = $this->createDummyConfig();
        $config['factory_class'] = DelayStrategyAwareAmqpConnectionFactorySpy::class;

        $connector->connect($config);

        $this->assertTrue($called);
    }

    public function testShouldCallContextCloseMethodOnWorkerStoppingEvent()
    {
        $contextMock = $this->createMock(AmqpContext::class);
        $contextMock
            ->expects($this->once())
            ->method('close')
        ;

        $dispatcherMock = $this->createMock(Dispatcher::class);
        $dispatcherMock
            ->expects($this->once())
            ->method('listen')
            ->with(WorkerStopping::class, $this->isInstanceOf(\Closure::class))
            ->willReturnCallback(function ($eventName, \Closure $listener) {
                $listener();
            })
        ;

        CustomContextAmqpConnectionFactoryMock::$context = $contextMock;

        $connector = new RabbitMQConnector($dispatcherMock);

        $config = $this->createDummyConfig();
        $config['factory_class'] = CustomContextAmqpConnectionFactoryMock::class;

        $connector->connect($config);
    }

    /**
     * @return array
     */
    private function createDummyConfig()
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
                'local_key'  => 'theLocalKey',
                'passphrase'  => 'thePassPhrase',
            ],
            'options' => [
                'exchange' => [
                    'name' => 'anExchangeName',
                    'declare' => false,
                    'type' => \Interop\Amqp\AmqpTopic::TYPE_DIRECT,
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
            'sleep_on_error' => env('RABBITMQ_ERROR_SLEEP', 5),
        ];
    }
}
