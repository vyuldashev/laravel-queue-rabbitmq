<?php

use PhpAmqpLib\Connection\AMQPSSLConnection;
use PHPUnit\Framework\TestCase;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnectorSSL;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQConnectorSSLTest extends TestCase
{
    public function test_connect()
    {
        $config = [
            'host'     => getenv('HOST'),
            'port'     => getenv('PORT_SSL'),
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
            'ssl_params' => [
                'cafile'        => getenv('RABBITMQ_SSL_CAFILE')
            ]
        ];

        $connector = new RabbitMQConnectorSSL();
        $queue = $connector->connect($config);

        $this->assertInstanceOf(RabbitMQQueue::class, $queue);
        $this->assertInstanceOf(AMQPSSLConnection::class, $connector->connection());
    }
}
