<?php

/**
 * default configuration for laravel-queue-rabbitmq merged with project config to base key 'queue'.
 *
 * @see \MapleSyrupGroup\AMQPEvents\Providers\AMQPEventServiceProvider
 */
return [

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

    'sleep_on_error' => env('RABBITMQ_ERROR_SLEEP', 5), // the number of seconds to sleep if there's an error communicating with rabbitmq

];
