<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Driver
    |--------------------------------------------------------------------------
    |
    | The Laravel queue API supports a variety of back-ends via an unified
    | API, giving you convenient access to each back-end using the same
    | syntax for each one. Here you may set the default queue driver.
    |
    | Supported: "null", "sync", "database", "beanstalkd",
    |            "sqs", "iron", "redis"
    |
    */

    'default' => env('QUEUE_DRIVER', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with Laravel. You are free to add more.
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table'  => 'jobs',
            'queue'  => 'default',
            'expire' => 60,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host'   => 'localhost',
            'queue'  => 'default',
            'ttr'    => 60,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key'    => 'your-public-key',
            'secret' => 'your-secret-key',
            'queue'  => 'your-queue-url',
            'region' => 'us-east-1',
        ],

        'iron' => [
            'driver'  => 'iron',
            'host'    => 'mq-aws-us-east-1.iron.io',
            'token'   => 'your-token',
            'project' => 'your-project-id',
            'queue'   => 'your-queue-name',
            'encrypt' => true,
        ],

        'redis' => [
            'driver' => 'redis',
            'queue'  => 'default',
            'expire' => 60,
        ],

        'rabbitmq' => [
            'driver' => 'rabbitmq',

            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => env('RABBITMQ_PORT', 5672),

            'vhost'    => env('RABBITMQ_VHOST', '/'),
            'login'    => env('RABBITMQ_LOGIN', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),

            'queue' => env('RABBITMQ_QUEUE'),
            // name of the default queue,
            'exchange_declare' => env('RABBITMQ_EXCHANGE_DECLARE', true),
            // create the exchange if not exists
            'queue_declare_bind' => env('RABBITMQ_QUEUE_DECLARE_BIND', true),
            // create the queue if not exists and bind to the exchange

            'queue_params' => [
                'passive'     => env('RABBITMQ_QUEUE_PASSIVE', false),
                'durable'     => env('RABBITMQ_QUEUE_DURABLE', true),
                'exclusive'   => env('RABBITMQ_QUEUE_EXCLUSIVE', false),
                'auto_delete' => env('RABBITMQ_QUEUE_AUTODELETE', false),
            ],

            'exchange_params' => [
                'name' => env('RABBITMQ_EXCHANGE_NAME', null),
                'type' => env('RABBITMQ_EXCHANGE_TYPE', 'direct'),
                // more info at http://www.rabbitmq.com/tutorials/amqp-concepts.html
                'passive' => env('RABBITMQ_EXCHANGE_PASSIVE', false),
                'durable' => env('RABBITMQ_EXCHANGE_DURABLE', true),
                // the exchange will survive server restarts
                'auto_delete' => env('RABBITMQ_EXCHANGE_AUTODELETE', false),
            ],

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control which database and table are used to store the jobs that
    | have failed. You may change them to any database / table you wish.
    |
    */

    'failed' => [
        'database' => 'mysql',
        'table'    => 'failed_jobs',
    ],

];
