<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Feature;

use Enqueue\AmqpLib\AmqpContext;
use PhpAmqpLib\Connection\AMQPSSLConnection;

/**
 * @group functional
 */
class SslQueueTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('queue.connections.rabbitmq.port', getenv('PORT_SSL'));
        $app['config']->set('queue.connections.rabbitmq.ssl_params', [
            'ssl_on' => true,
            'cafile' => getenv('RABBITMQ_SSL_CAFILE'),
            'local_cert' => null,
            'local_key' => null,
            'verify_peer' => false,
            'passphrase' => null,
        ]);
    }

    public function testConnection(): void
    {
        /** @var AmqpContext $context */
        $context = $this->connection()->getContext();

        $this->assertInstanceOf(AmqpContext::class, $context);
        $this->assertInstanceOf(AMQPSSLConnection::class, $context->getLibChannel()->getConnection());
        $this->assertTrue($context->getLibChannel()->getConnection()->isConnected());
    }
}
