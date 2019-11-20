<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Feature;

use Enqueue\AmqpLib\AmqpContext;
use PhpAmqpLib\Connection\AMQPLazyConnection;

class QueueTest extends TestCase
{
    public function testConnection(): void
    {
        /** @var AmqpContext $context */
        $context = $this->connection()->getContext();

        $this->assertInstanceOf(AmqpContext::class, $context);
        $this->assertInstanceOf(AMQPLazyConnection::class, $context->getLibChannel()->getConnection());
        $this->assertTrue($context->getLibChannel()->getConnection()->isConnected());
    }
}
