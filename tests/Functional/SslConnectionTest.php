<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Functional;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Enqueue\AmqpLib\AmqpContext;
use Illuminate\Events\Dispatcher;
use Interop\Amqp\AmqpTopic;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PHPUnit\Framework\TestCase;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class SslConnectionTest extends TestCase
{
    public function testConnectorEstablishSecureConnectionWithRabbitMQBroker()
    {
        $config = [
            'factory_class' => AmqpConnectionFactory::class,
            'dsn'      => null,
            'host'     => getenv('HOST'),
            'port'     => getenv('PORT_SSL'),
            'login'    => 'guest',
            'password' => 'guest',
            'vhost'    => '/',

            'queue'              => 'queue_name',
            'exchange_declare'   => true,
            'queue_declare'   => true,
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
                'type'        => AmqpTopic::TYPE_DIRECT,
                'passive'     => false,
                'durable'     => true,
                'auto_delete' => false,
            ],
            'ssl_params' => [
                'ssl_on'        => true,
                'cafile'        => getenv('RABBITMQ_SSL_CAFILE'),
                'local_cert'    => null,
                'verify_peer'   => false,
                'passphrase'    => null,
            ]
        ];

        $connector = new RabbitMQConnector(new Dispatcher());
        /** @var RabbitMQQueue $queue */
        $queue = $connector->connect($config);

        $this->assertInstanceOf(RabbitMQQueue::class, $queue);

        /** @var AmqpContext $context */
        $context = $queue->getContext();
        $this->assertInstanceOf(AmqpContext::class, $context);

        $this->assertInstanceOf(AMQPSSLConnection::class, $context->getLibChannel()->getConnection());
        $this->assertTrue($context->getLibChannel()->getConnection()->isConnected());
    }
}
