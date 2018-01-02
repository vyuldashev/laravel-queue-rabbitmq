<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue;

use Illuminate\Container\Container;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpProducer;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;

class RabbitMQQueueTest extends TestCase
{
    public function testShouldImplementQueueInterface()
    {
        $rc = new \ReflectionClass(RabbitMQQueue::class);

        $this->assertTrue($rc->implementsInterface(\Illuminate\Contracts\Queue\Queue::class));
    }

    public function testShouldBeSubClassOfQueue()
    {
        $rc = new \ReflectionClass(RabbitMQQueue::class);

        $this->assertTrue($rc->isSubclassOf(\Illuminate\Queue\Queue::class));
    }

    public function testCouldBeConstructedWithExpectedArguments()
    {
        new RabbitMQQueue($this->createAmqpContext(), $this->createDummyConfig());
    }

    public function testShouldGenerateNewCorrelationIdIfNotSet()
    {
        $queue = new RabbitMQQueue($this->createAmqpContext(), $this->createDummyConfig());

        $firstId = $queue->getCorrelationId();
        $secondId = $queue->getCorrelationId();

        $this->assertNotEmpty($firstId);
        $this->assertNotEmpty($secondId);
        $this->assertNotSame($firstId, $secondId);
    }

    public function testShouldReturnPreviouslySetCorrelationId()
    {
        $expectedId = 'theCorrelationId';

        $queue = new RabbitMQQueue($this->createAmqpContext(), $this->createDummyConfig());

        $queue->setCorrelationId($expectedId);

        $this->assertSame($expectedId, $queue->getCorrelationId());
        $this->assertSame($expectedId, $queue->getCorrelationId());
    }

    public function testShouldAllowGetContextSetInConstructor()
    {
        $context = $this->createAmqpContext();

        $queue = new RabbitMQQueue($context, $this->createDummyConfig());

        $this->assertSame($context, $queue->getContext());
    }

    public function testShouldReturnExpectedNumberOfMessages()
    {
        $expectedQueueName = 'theQueueName';
        $queue = $this->createMock(AmqpQueue::class);
        $expectedCount = 123321;

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($this->createMock(AmqpTopic::class))
        ;
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->with($expectedQueueName)
            ->willReturn($queue)
        ;
        $context
            ->expects($this->once())
            ->method('declareQueue')
            ->with($this->identicalTo($queue))
            ->willReturn($expectedCount)
        ;

        $queue = new RabbitMQQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $this->assertSame($expectedCount, $queue->size($expectedQueueName));
    }

    public function testShouldSendExpectedMessageOnPushRaw()
    {
        $expectedQueueName = 'theQueueName';
        $expectedBody = 'thePayload';
        $topic = $this->createMock(AmqpTopic::class);

        $queue = $this->createMock(AmqpQueue::class);
        $queue->expects($this->any())->method('getQueueName')->willReturn('theQueueName');

        $producer = $this->createMock(AmqpProducer::class);
        $producer
            ->expects($this->once())
            ->method('send')
            ->with($this->identicalTo($topic), $this->isInstanceOf(AmqpMessage::class))
            ->willReturnCallback(function ($actualTopic, AmqpMessage $message) use ($expectedQueueName, $expectedBody, $topic) {
                $this->assertSame($topic, $actualTopic);
                $this->assertSame($expectedBody, $message->getBody());
                $this->assertSame($expectedQueueName, $message->getRoutingKey());
                $this->assertSame('application/json', $message->getContentType());
                $this->assertSame(AmqpMessage::DELIVERY_MODE_PERSISTENT, $message->getDeliveryMode());
                $this->assertNotEmpty($message->getCorrelationId());
                $this->assertNull($message->getProperty(RabbitMQJob::ATTEMPT_COUNT_HEADERS_KEY));
            })
        ;
        $producer
            ->expects($this->never())
            ->method('setDeliveryDelay')
        ;

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($topic)
        ;
        $context
            ->expects($this->once())
            ->method('createMessage')
            ->with($expectedBody)
            ->willReturn(new \Interop\Amqp\Impl\AmqpMessage($expectedBody))
        ;

        $context
            ->expects($this->once())
            ->method('createQueue')
            ->with($expectedQueueName)
            ->willReturn($queue)
        ;
        $context
            ->expects($this->once())
            ->method('createProducer')
            ->willReturn($producer)
        ;

        $queue = new RabbitMQQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $queue->pushRaw('thePayload', $expectedQueueName);
    }

    public function testShouldSetAttemptCountPropIfNotNull()
    {
        $expectedAttempts = 54321;

        $topic = $this->createMock(AmqpTopic::class);

        $producer = $this->createMock(AmqpProducer::class);
        $producer
            ->expects($this->once())
            ->method('send')
            ->with($this->identicalTo($topic), $this->isInstanceOf(AmqpMessage::class))
            ->willReturnCallback(function ($actualTopic, AmqpMessage $message) use ($expectedAttempts) {
                $this->assertSame($expectedAttempts, $message->getProperty(RabbitMQJob::ATTEMPT_COUNT_HEADERS_KEY));
            })
        ;
        $producer
            ->expects($this->never())
            ->method('setDeliveryDelay')
        ;

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($topic)
        ;
        $context
            ->expects($this->once())
            ->method('createMessage')
            ->with()
            ->willReturn(new \Interop\Amqp\Impl\AmqpMessage())
        ;
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn($this->createMock(AmqpQueue::class))
        ;
        $context
            ->expects($this->once())
            ->method('createProducer')
            ->willReturn($producer)
        ;

        $queue = new RabbitMQQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $queue->pushRaw('thePayload', 'aQueue', ['attempts' => $expectedAttempts]);
    }

    public function testShouldSetDeliveryDelayIfDelayOptionPresent()
    {
        $expectedDelay = 56;
        $expectedDeliveryDelay = 56000;

        $topic = $this->createMock(AmqpTopic::class);

        $producer = $this->createMock(AmqpProducer::class);
        $producer
            ->expects($this->once())
            ->method('send')
        ;
        $producer
            ->expects($this->once())
            ->method('setDeliveryDelay')
            ->with($expectedDeliveryDelay)
        ;

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($topic)
        ;
        $context
            ->expects($this->once())
            ->method('createMessage')
            ->with()
            ->willReturn(new \Interop\Amqp\Impl\AmqpMessage())
        ;
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn($this->createMock(AmqpQueue::class))
        ;
        $context
            ->expects($this->once())
            ->method('createProducer')
            ->willReturn($producer)
        ;

        $queue = new RabbitMQQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $queue->pushRaw('thePayload', 'aQueue', ['delay' => $expectedDelay]);
    }

    public function testShouldLogExceptionOnPushRaw()
    {
        $producer = $this->createMock(AmqpProducer::class);
        $producer
            ->expects($this->once())
            ->method('send')
            ->willReturnCallback(function () {
                throw new \LogicException('Something went wrong while sending a message');
            })
        ;

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($this->createMock(AmqpTopic::class))
        ;
        $context
            ->expects($this->once())
            ->method('createMessage')
            ->willReturn($this->createMock(AmqpMessage::class))
        ;
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn($this->createMock(AmqpQueue::class))
        ;
        $context
            ->expects($this->once())
            ->method('createProducer')
            ->willReturn($producer)
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('AMQP error while attempting pushRaw: Something went wrong while sending a message')
        ;

        $container = new Container();
        $container['log'] = $logger;


        $queue = new RabbitMQQueue($context, $this->createDummyConfig());
        $queue->setContainer($container);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error writing data to the connection with RabbitMQ');
        $queue->pushRaw('thePayload', 'aQueue');
    }

    public function testShouldReturnNullIfNoMessagesOnQueue()
    {
        $queue = $this->createMock(AmqpQueue::class);

        $consumer = $this->createMock(AmqpConsumer::class);
        $consumer
            ->expects($this->once())
            ->method('receiveNoWait')
            ->willReturn(null)
        ;

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($this->createMock(AmqpTopic::class))
        ;
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn($queue)
        ;
        $context
            ->expects($this->once())
            ->method('createConsumer')
            ->with($this->identicalTo($queue))
            ->willReturn($consumer)
        ;

        $queue = new RabbitMQQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $this->assertNull($queue->pop('aQueue'));
    }

    public function testShouldReturnRabbitMQJobIfMessageReceivedFromQueue()
    {
        $queue = $this->createMock(AmqpQueue::class);

        $message = new \Interop\Amqp\Impl\AmqpMessage('thePayload');

        $consumer = $this->createMock(AmqpConsumer::class);
        $consumer
            ->expects($this->once())
            ->method('receiveNoWait')
            ->willReturn($message)
        ;
        $consumer
            ->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue)
        ;

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($this->createMock(AmqpTopic::class))
        ;
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn($queue)
        ;
        $context
            ->expects($this->once())
            ->method('createConsumer')
            ->with($this->identicalTo($queue))
            ->willReturn($consumer)
        ;

        $queue = new RabbitMQQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $job = $queue->pop('aQueue');

        $this->assertInstanceOf(RabbitMQJob::class, $job);
    }

    public function testShouldLogExceptionOnPop()
    {
        $queue = $this->createMock(AmqpQueue::class);

        $consumer = $this->createMock(AmqpConsumer::class);
        $consumer
            ->expects($this->once())
            ->method('receiveNoWait')
            ->willReturnCallback(function () {
                throw new \LogicException('Something went wrong while receiving a message');
            })
        ;

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($this->createMock(AmqpTopic::class))
        ;
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn($queue)
        ;
        $context
            ->expects($this->once())
            ->method('createConsumer')
            ->with($this->identicalTo($queue))
            ->willReturn($consumer)
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('AMQP error while attempting pop: Something went wrong while receiving a message')
        ;

        $container = new Container();
        $container['log'] = $logger;

        $queue = new RabbitMQQueue($context, $this->createDummyConfig());
        $queue->setContainer($container);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error writing data to the connection with RabbitMQ');
        $queue->pop('aQueue');
    }

    /**
     * @return AmqpContext|\PHPUnit_Framework_MockObject_MockObject|AmqpContext
     */
    private function createAmqpContext()
    {
        return $this->createMock(AmqpContext::class);
    }

    private function createDummyContainer()
    {
        $logger = $this->createMock(LoggerInterface::class);

        $container = new Container();
        $container['log'] = $logger;

        return $container;
    }

    /**
     * @return array
     */
    private function createDummyConfig()
    {
        return [
            'dsn' => 'aDsn',
            'host' => 'aHost',
            'port' => 'aPort',
            'login' => 'aLogin',
            'password' => 'aPassword',
            'vhost' => 'aVhost',
            'ssl_params' => [
                'ssl_on' => 'aSslOn',
                'verify_peer' => 'aVerifyPeer',
                'cafile' => 'aCafile',
                'local_cert' => 'aLocalCert',
                'local_key'  => 'aLocalKey',
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
            'sleep_on_error' => false,
        ];
    }
}
