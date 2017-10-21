<?php

use PhpAmqpLib\Connection\AMQPSocketConnection;
use PHPUnit\Framework\TestCase;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnectorSocket;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQConnectorSocketTest extends TestCase
{
    public function testConnect()
    {
        $config = [
            'host'     => getenv('HOST'),
            'port'     => getenv('PORT'),
            'login'    => 'guest',
            'password' => 'guest',
            'vhost'    => '/',

            'queue'              => 'queue_name',
            'exchange_declare'   => true,
            'queue_declare_bind' => true,

            'queue_params' => [
                'passive'     => false,
                'durable'     => true,
                'exclusive'   => false,
                'auto_delete' => false,
                'arguments'   => null,
            ],
            'exchange_params' => [
                'name'        => null,
                'type'        => 'direct',
                'passive'     => false,
                'durable'     => true,
                'auto_delete' => false,
            ],

            'connection_method_socket' => true,
        ];

        $connector = new RabbitMQConnectorSocket();
        $queue = $connector->connect($config);

        $this->assertInstanceOf(RabbitMQQueue::class, $queue);
        $this->assertInstanceOf(AMQPSocketConnection::class, $connector->connection());
    }
}
