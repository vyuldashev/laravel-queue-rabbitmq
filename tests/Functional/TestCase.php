<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Functional;

use PhpAmqpLib\Connection\AMQPLazyConnection;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('queue.default', 'rabbitmq');
        $app['config']->set('queue.connections.rabbitmq', [
            'driver' => 'rabbitmq',
            'queue' => 'order',
            'connection' => AMQPLazyConnection::class,

            'hosts' => [
                [
                    'host' => getenv('HOST'),
                    'port' => getenv('PORT'),
                    'vhost' => '/',
                    'user' => 'guest',
                    'password' => 'guest',
                ],
            ],

            'options' => [
                'ssl_options' => [
                    'cafile' => null,
                    'local_cert' => null,
                    'local_key' => null,
                    'verify_peer' => true,
                    'passphrase' => null,
                ],
            ],

            'worker' => 'default',

        ]);
        $app['config']->set('queue.connections.rabbitmq-with-options', [
            'driver' => 'rabbitmq',
            'queue' => 'order',
            'connection' => AMQPLazyConnection::class,

            'hosts' => [
                [
                    'host' => getenv('HOST'),
                    'port' => getenv('PORT'),
                    'vhost' => '/',
                    'user' => 'guest',
                    'password' => 'guest',
                ],
            ],

            'options' => [
                'ssl_options' => [
                    'cafile' => null,
                    'local_cert' => null,
                    'local_key' => null,
                    'verify_peer' => true,
                    'passphrase' => null,
                ],

                'queue' => [
                    'prioritize_delayed' => true,
                    'queue_max_priority' => 20,
                    'exchange' => 'application-x',
                    'exchange_type' => 'topic',
                    'exchange_routing_key' => 'process.%s',
                    'reroute_failed' => true,
                    'failed_exchange' => 'failed-exchange',
                    'failed_routing_key' => 'application-x.%s.failed',
                ],
            ],

            'worker' => 'default',

        ]);
        $app['config']->set('queue.connections.rabbitmq-with-options-empty', [
            'driver' => 'rabbitmq',
            'queue' => 'order',
            'connection' => AMQPLazyConnection::class,

            'hosts' => [
                [
                    'host' => getenv('HOST'),
                    'port' => getenv('PORT'),
                    'vhost' => '/',
                    'user' => 'guest',
                    'password' => 'guest',
                ],
            ],

            'options' => [
                'ssl_options' => [
                    'cafile' => null,
                    'local_cert' => null,
                    'local_key' => null,
                    'verify_peer' => true,
                    'passphrase' => null,
                ],

                'queue' => [
                    'prioritize_delayed' => '',
                    'queue_max_priority' => '',
                    'exchange' => '',
                    'exchange_type' => '',
                    'exchange_routing_key' => '',
                    'reroute_failed' => '',
                    'failed_exchange' => '',
                    'failed_routing_key' => '',
                ],
            ],

            'worker' => 'default',

        ]);
    }

    /**
     * @param $object
     * @param string $method
     * @param array $parameters
     * @return mixed
     * @throws \Exception
     */
    protected function callMethod($object, string $method, array $parameters = [])
    {
        try {
            $className = get_class($object);
            $reflection = new \ReflectionClass($className);
        } catch (\ReflectionException $e) {
            throw new \Exception($e->getMessage());
        }

        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
