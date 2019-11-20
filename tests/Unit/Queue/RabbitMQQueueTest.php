<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Unit\Queue;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpProducer;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Queue\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQQueueTest extends TestCase
{
    public function testShouldImplementQueueInterface(): void
    {
        $rc = new ReflectionClass(RabbitMQQueue::class);

        $this->assertTrue($rc->implementsInterface(QueueContract::class));
    }

    public function testShouldBeSubClassOfQueue(): void
    {
        $rc = new ReflectionClass(RabbitMQQueue::class);

        $this->assertTrue($rc->isSubclassOf(Queue::class));
    }

    public function testCouldBeConstructedWithExpectedArguments(): void
    {
        new RabbitMQQueue($this->createAmqpContext(), $this->createDummyConfig());
    }

    public function testShouldGenerateNewCorrelationIdIfNotSet(): void
    {
        $queue = new RabbitMQQueue($this->createAmqpContext(), $this->createDummyConfig());

        $firstId = $queue->getCorrelationId();
        $secondId = $queue->getCorrelationId();

        $this->assertNotEmpty($firstId);
        $this->assertNotEmpty($secondId);
        $this->assertNotSame($firstId, $secondId);
    }

    public function testShouldReturnPreviouslySetCorrelationId(): void
    {
        $expectedId = 'theCorrelationId';

        $queue = new RabbitMQQueue($this->createAmqpContext(), $this->createDummyConfig());

        $queue->setCorrelationId($expectedId);

        $this->assertSame($expectedId, $queue->getCorrelationId());
        $this->assertSame($expectedId, $queue->getCorrelationId());
    }

    public function testShouldAllowGetContextSetInConstructor(): void
    {
        $context = $this->createAmqpContext();

        $queue = new RabbitMQQueue($context, $this->createDummyConfig());

        $this->assertSame($context, $queue->getContext());
    }

    public function testShouldReturnExpectedNumberOfMessages(): void
    {
        $expectedQueueName = 'theQueueName';
        $queue = $this->createMock(AmqpQueue::class);
        $expectedCount = 123321;

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($this->createMock(AmqpTopic::class));
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->with($expectedQueueName)
            ->willReturn($queue);
        $context
            ->expects($this->once())
            ->method('declareQueue')
            ->with($this->identicalTo($queue))
            ->willReturn($expectedCount);

        $queue = new RabbitMQQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $this->assertSame($expectedCount, $queue->size($expectedQueueName));
    }

    /**
     * @throws Exception
     */
    public function testShouldSendExpectedMessageOnPushRaw(): void
    {
        $expectedQueueName = 'theQueueName';
        $expectedBody = 'thePayload';
        $topic = $this->createMock(AmqpTopic::class);

        $queue = $this->createMock(AmqpQueue::class);
        $queue->method('getQueueName')->willReturn('theQueueName');

        $producer = $this->createMock(AmqpProducer::class);
        $producer
            ->expects($this->once())
            ->method('send')
            ->with($this->identicalTo($topic), $this->isInstanceOf(AmqpMessage::class))
            ->willReturnCallback(function ($actualTopic, AmqpMessage $message) use ($expectedQueueName, $expectedBody, $topic): void {
                $this->assertSame($topic, $actualTopic);
                $this->assertSame($expectedBody, $message->getBody());
                $this->assertSame($expectedQueueName, $message->getRoutingKey());
                $this->assertSame('application/json', $message->getContentType());
                $this->assertSame(AmqpMessage::DELIVERY_MODE_PERSISTENT, $message->getDeliveryMode());
                $this->assertNotEmpty($message->getCorrelationId());
                $this->assertNull($message->getProperty(RabbitMQJob::ATTEMPT_COUNT_HEADERS_KEY));
            });
        $producer
            ->expects($this->never())
            ->method('setDeliveryDelay');

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($topic);
        $context
            ->expects($this->once())
            ->method('createMessage')
            ->with($expectedBody)
            ->willReturn(new \Interop\Amqp\Impl\AmqpMessage($expectedBody));

        $context
            ->expects($this->once())
            ->method('createQueue')
            ->with($expectedQueueName)
            ->willReturn($queue);
        $context
            ->expects($this->once())
            ->method('createProducer')
            ->willReturn($producer);

        $queue = new RabbitMQQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $queue->pushRaw('thePayload', $expectedQueueName);
    }

    /**
     * @throws Exception
     */
    public function testShouldSetAttemptCountPropIfNotNull(): void
    {
        $expectedAttempts = 54321;

        $topic = $this->createMock(AmqpTopic::class);

        $producer = $this->createMock(AmqpProducer::class);
        $producer
            ->expects($this->once())
            ->method('send')
            ->with($this->identicalTo($topic), $this->isInstanceOf(AmqpMessage::class))
            ->willReturnCallback(function ($actualTopic, AmqpMessage $message) use ($expectedAttempts): void {
                $this->assertSame($expectedAttempts, $message->getProperty(RabbitMQJob::ATTEMPT_COUNT_HEADERS_KEY));
            });
        $producer
            ->expects($this->never())
            ->method('setDeliveryDelay');

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($topic);
        $context
            ->expects($this->once())
            ->method('createMessage')
            ->with()
            ->willReturn(new \Interop\Amqp\Impl\AmqpMessage());
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn($this->createMock(AmqpQueue::class));
        $context
            ->expects($this->once())
            ->method('createProducer')
            ->willReturn($producer);

        $queue = new RabbitMQQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $queue->pushRaw('thePayload', 'aQueue', ['attempts' => $expectedAttempts]);
    }

    /**
     * @throws Exception
     */
    public function testShouldSetDeliveryDelayIfDelayOptionPresent(): void
    {
        $expectedDelay = 56;
        $expectedDeliveryDelay = 56000;

        $topic = $this->createMock(AmqpTopic::class);

        $producer = $this->createMock(AmqpProducer::class);
        $producer
            ->expects($this->once())
            ->method('send');
        $producer
            ->expects($this->once())
            ->method('setDeliveryDelay')
            ->with($expectedDeliveryDelay);

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($topic);
        $context
            ->expects($this->once())
            ->method('createMessage')
            ->with()
            ->willReturn(new \Interop\Amqp\Impl\AmqpMessage());
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn($this->createMock(AmqpQueue::class));
        $context
            ->expects($this->once())
            ->method('createProducer')
            ->willReturn($producer);

        $queue = new RabbitMQQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $queue->pushRaw('thePayload', 'aQueue', ['delay' => $expectedDelay]);
    }

    public function testShouldReturnNullIfNoMessagesOnQueue(): void
    {
        $queue = $this->createMock(AmqpQueue::class);

        $consumer = $this->createMock(AmqpConsumer::class);
        $consumer
            ->expects($this->once())
            ->method('receiveNoWait')
            ->willReturn(null);

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($this->createMock(AmqpTopic::class));
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn($queue);
        $context
            ->expects($this->once())
            ->method('createConsumer')
            ->with($this->identicalTo($queue))
            ->willReturn($consumer);

        $queue = new RabbitMQQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $this->assertNull($queue->pop('aQueue'));
    }

    public function testShouldReturnRabbitMQJobIfMessageReceivedFromQueue(): void
    {
        $queue = $this->createMock(AmqpQueue::class);

        $message = new \Interop\Amqp\Impl\AmqpMessage('thePayload');

        $consumer = $this->createMock(AmqpConsumer::class);
        $consumer
            ->expects($this->once())
            ->method('receiveNoWait')
            ->willReturn($message);
        $consumer
            ->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue);

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($this->createMock(AmqpTopic::class));
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn($queue);
        $context
            ->expects($this->once())
            ->method('createConsumer')
            ->with($this->identicalTo($queue))
            ->willReturn($consumer);

        $queue = new RabbitMQQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $job = $queue->pop('aQueue');

        $this->assertInstanceOf(RabbitMQJob::class, $job);
    }

    /**
     * @return AmqpContext|MockObject|AmqpContext
     */
    private function createAmqpContext()
    {
        return $this->createMock(AmqpContext::class);
    }

    private function createDummyContainer(): Container
    {
        return new Container();
    }

    /**
     * @return array
     */
    private function createDummyConfig(): array
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
                'local_key' => 'aLocalKey',
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
            'sleep_on_error' => false,
        ];
    }
}
