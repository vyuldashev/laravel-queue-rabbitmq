<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Feature;

use PhpAmqpLib\Connection\AMQPSSLConnection;

/**
 * @group functional
 */
class SslQueueTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('queue.default', 'rabbitmq');
        $app['config']->set('queue.connections.rabbitmq', [
            'driver' => 'rabbitmq',
            'queue' => 'default',
            'connection' => AMQPSSLConnection::class,

            'hosts' => [
                [
                    'host' => getenv('HOST'),
                    'port' => getenv('PORT_SSL'),
                    'vhost' => '/',
                    'user' => 'guest',
                    'password' => 'guest',
                ],
            ],

            'options' => [
                'ssl_options' => [
                    'cafile' => getenv('RABBITMQ_SSL_CAFILE'),
                    'local_cert' => null,
                    'local_key' => null,
                    'verify_peer' => true,
                    'passphrase' => null,
                ],
            ],

            'worker' => 'default',

        ]);
    }

    public function testConnection(): void
    {
        $this->assertInstanceOf(AMQPSSLConnection::class, $this->connection()->getChannel()->getConnection());
    }
}
