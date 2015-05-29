<?php

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageAliases($app) {
        return [
            'LaravelQueueRabbitMQServiceProvider' => 'FintechFab\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider',
        ];
    }

    public function getBaseConfig($merge) {
        return [
            'default' => 'rabbitmq',
            'connections' => [
                'rabbitmq' => array_merge([
                    'driver'          => 'rabbitmq',

                    'host'            => env('RABBITMQ_HOST', '127.0.0.1'),
                    'port'            => env('RABBITMQ_PORT', 5672),

                    'vhost'           => env('RABBITMQ_VHOST', '/'),
                    'login'           => env('RABBITMQ_LOGIN', 'guest'),
                    'password'        => env('RABBITMQ_PASSWORD', 'guest'),

                    'queue'           => env('RABBITMQ_QUEUE'),

                    'queue_params'    => [
                        'passive'     => env('RABBITMQ_QUEUE_PASSIVE', false),
                        'durable'     => env('RABBITMQ_QUEUE_DURABLE', true),
                        'exclusive'   => env('RABBITMQ_QUEUE_EXCLUSIVE', false),
                        'auto_delete' => env('RABBITMQ_QUEUE_AUTODELETE', false),
                    ],

                    'queues_params'   => [
                    ],

                    'exchange_params' => [
                        'type'        => env('RABBITMQ_EXCHANGE_TYPE', 'direct'),
                        'passive'     => env('RABBITMQ_EXCHANGE_PASSIVE', false),
                        'durable'     => env('RABBITMQ_EXCHANGE_DURABLE', true),
                        'auto_delete' => env('RABBITMQ_EXCHANGE_AUTODELETE', false),
                    ],
                ], $merge),
            ],
        ];
    }
}
