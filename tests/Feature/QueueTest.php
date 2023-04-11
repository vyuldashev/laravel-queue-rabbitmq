<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Feature;

use PhpAmqpLib\Connection\AMQPStreamConnection;

class QueueTest extends TestCase
{
    public function testConnection(): void
    {
        $this->assertInstanceOf(AMQPStreamConnection::class, $this->connection()->getChannel()->getConnection());
    }
}
