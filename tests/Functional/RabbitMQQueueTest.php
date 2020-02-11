<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Functional;

use PhpAmqpLib\Exchange\AMQPExchangeType;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Functional\TestCase as BaseTestCase;

class RabbitMQQueueTest extends BaseTestCase
{
    public function testConnection(): void
    {
        /** @var $queue RabbitMQQueue */
        $queue = $this->connection();
        $this->assertInstanceOf(RabbitMQQueue::class, $queue);

        $queue = $this->connection('rabbitmq-with-options');
        $this->assertInstanceOf(RabbitMQQueue::class, $queue);

        $queue = $this->connection('rabbitmq-with-options-empty');
        $this->assertInstanceOf(RabbitMQQueue::class, $queue);
    }

    public function testRerouteFailed(): void
    {
        /** @var $queue RabbitMQQueue */
        $queue = $this->connection();
        $this->assertFalse($this->callMethod($queue, 'isRerouteFailed'));

        $queue = $this->connection('rabbitmq-with-options');
        $this->assertTrue($this->callMethod($queue, 'isRerouteFailed'));

        $queue = $this->connection('rabbitmq-with-options-empty');
        $this->assertFalse($this->callMethod($queue, 'isRerouteFailed'));
    }

    public function testPrioritizeDelayed(): void
    {
        /** @var $queue RabbitMQQueue */
        $queue = $this->connection();
        $this->assertFalse($this->callMethod($queue, 'isPrioritizeDelayed'));

        $queue = $this->connection('rabbitmq-with-options');
        $this->assertTrue($this->callMethod($queue, 'isPrioritizeDelayed'));

        $queue = $this->connection('rabbitmq-with-options-empty');
        $this->assertFalse($this->callMethod($queue, 'isPrioritizeDelayed'));
    }

    public function testQueueMaxPriority(): void
    {
        /** @var $queue RabbitMQQueue */
        $queue = $this->connection();
        $this->assertIsInt($this->callMethod($queue, 'getQueueMaxPriority'));
        $this->assertSame(2, $this->callMethod($queue, 'getQueueMaxPriority'));

        $queue = $this->connection('rabbitmq-with-options');
        $this->assertIsInt($this->callMethod($queue, 'getQueueMaxPriority'));
        $this->assertSame(20, $this->callMethod($queue, 'getQueueMaxPriority'));

        $queue = $this->connection('rabbitmq-with-options-empty');
        $this->assertIsInt($this->callMethod($queue, 'getQueueMaxPriority'));
        $this->assertSame(2, $this->callMethod($queue, 'getQueueMaxPriority'));
    }

    public function testExchangeType(): void
    {
        /** @var $queue RabbitMQQueue */
        $queue = $this->connection();
        $this->assertSame(AMQPExchangeType::DIRECT, $this->callMethod($queue, 'getExchangeType'));
        $this->assertSame(AMQPExchangeType::DIRECT, $this->callMethod($queue, 'getExchangeType', ['']));
        $this->assertSame(AMQPExchangeType::DIRECT, $this->callMethod($queue, 'getExchangeType', ['test']));
        $this->assertSame(AMQPExchangeType::TOPIC, $this->callMethod($queue, 'getExchangeType', ['topic']));

        $queue = $this->connection('rabbitmq-with-options');
        $this->assertSame(AMQPExchangeType::TOPIC, $this->callMethod($queue, 'getExchangeType'));

        $queue = $this->connection('rabbitmq-with-options-empty');
        $this->assertSame(AMQPExchangeType::DIRECT, $this->callMethod($queue, 'getExchangeType'));
    }

    public function testExchange(): void
    {
        /** @var $queue RabbitMQQueue */
        $queue = $this->connection();
        $this->assertSame('test', $this->callMethod($queue, 'getExchange', ['test']));
        $this->assertNull($this->callMethod($queue, 'getExchange', ['']));
        $this->assertNull($this->callMethod($queue, 'getExchange'));

        $queue = $this->connection('rabbitmq-with-options');
        $this->assertNotNull($this->callMethod($queue, 'getExchange'));
        $this->assertSame('application-x', $this->callMethod($queue, 'getExchange'));

        $queue = $this->connection('rabbitmq-with-options-empty');
        $this->assertNull($this->callMethod($queue, 'getExchange'));
    }

    public function testFailedExchange(): void
    {
        /** @var $queue RabbitMQQueue */
        $queue = $this->connection();
        $this->assertSame('test', $this->callMethod($queue, 'getFailedExchange', ['test']));
        $this->assertNull($this->callMethod($queue, 'getExchange', ['']));
        $this->assertNull($this->callMethod($queue, 'getFailedExchange'));

        $queue = $this->connection('rabbitmq-with-options');
        $this->assertNotNull($this->callMethod($queue, 'getFailedExchange'));
        $this->assertSame('failed-exchange', $this->callMethod($queue, 'getFailedExchange'));

        $queue = $this->connection('rabbitmq-with-options-empty');
        $this->assertNull($this->callMethod($queue, 'getFailedExchange'));
    }

    public function testRoutingKey(): void
    {
        /** @var $queue RabbitMQQueue */
        $queue = $this->connection();
        $this->assertSame('test', $this->callMethod($queue, 'getRoutingKey', ['test']));
        $this->assertSame('', $this->callMethod($queue, 'getRoutingKey', ['']));

        $queue = $this->connection('rabbitmq-with-options');
        $this->assertSame('process.test', $this->callMethod($queue, 'getRoutingKey', ['test']));

        $queue = $this->connection('rabbitmq-with-options-empty');
        $this->assertSame('test', $this->callMethod($queue, 'getRoutingKey', ['test']));
    }

    public function testFailedRoutingKey(): void
    {
        /** @var $queue RabbitMQQueue */
        $queue = $this->connection();
        $this->assertSame('test.failed', $this->callMethod($queue, 'getFailedRoutingKey', ['test']));
        $this->assertSame('failed', $this->callMethod($queue, 'getFailedRoutingKey', ['']));

        $queue = $this->connection('rabbitmq-with-options');
        $this->assertSame('application-x.test.failed', $this->callMethod($queue, 'getFailedRoutingKey', ['test']));

        $queue = $this->connection('rabbitmq-with-options-empty');
        $this->assertSame('test.failed', $this->callMethod($queue, 'getFailedRoutingKey', ['test']));
    }

    public function testQueueArguments(): void
    {
        $this->assertTrue(true);
    }

    public function testDelayQueueArguments(): void
    {
        $this->assertTrue(true);
    }
}
