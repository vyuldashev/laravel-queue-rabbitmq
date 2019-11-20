<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Illuminate\Support\Facades\Queue;
use Interop\Amqp\AmqpTopic;
use Orchestra\Testbench\TestCase as BaseTestCase;
use VladimirYuldashev\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelQueueRabbitMQServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('queue.default', 'rabbitmq');
        $app['config']->set('queue.connections.rabbitmq', [
            'driver' => 'rabbitmq',
            'worker' => 'default',
            'dsn' => null,
            'factory_class' => AmqpConnectionFactory::class,
            'host' => getenv('HOST'),
            'port' => getenv('PORT'),
            'vhost' => '/',
            'login' => 'guest',
            'password' => 'guest',
            'queue' => 'default',

            'options' => [
                'exchange' => [
                    'name' => null,
                    'declare' => true,
                    'type' => AmqpTopic::TYPE_DIRECT,
                    'passive' => false,
                    'durable' => true,
                    'auto_delete' => false,
                    'arguments' => '[]',
                ],

                'queue' => [
                    'declare' => true,
                    'bind' => true,
                    'passive' => false,
                    'durable' => true,
                    'exclusive' => false,
                    'auto_delete' => false,
                    'arguments' => '[]',
                ],
            ],

            'ssl_params' => [
                'ssl_on' => false,
                'cafile' => null,
                'local_cert' => null,
                'local_key' => null,
                'verify_peer' => true,
                'passphrase' => null,
            ],
        ]);
    }

    protected function connection(): RabbitMQQueue
    {
        return Queue::connection();
    }
}
