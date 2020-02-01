<?php

/**
 * This is an example of queue connection configuration.
 * It will be merged into config/queue.php.
 * You need to set proper values in `.env`.
 */
return [

    'driver' => 'rabbitmq',
    'queue' => env('RABBITMQ_QUEUE', 'default'),
    'connection' => PhpAmqpLib\Connection\AMQPLazyConnection::class,

    'hosts' => [
        [
            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
        ],
    ],

    'options' => [
        'ssl_options' => [
            'cafile' => env('RABBITMQ_SSL_CAFILE', null),
            'local_cert' => env('RABBITMQ_SSL_LOCALCERT', null),
            'local_key' => env('RABBITMQ_SSL_LOCALKEY', null),
            'verify_peer' => env('RABBITMQ_SSL_VERIFY_PEER', true),
            'passphrase' => env('RABBITMQ_SSL_PASSPHRASE', null),
        ],
    ],

    /*
     * Set to "horizon" if you wish to use Laravel Horizon.
     */
    'worker' => env('RABBITMQ_WORKER', 'default'),

    /*
     * ## Manage the delay strategy from the config.
     *
     * The delay strategy can be set to:
     *  - \VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools\DlxDelayStrategy::class
     *
     * ### Backoff Strategy
     *
     * The `DlxDelayStrategy` is BackoffAware and by default a ConstantBackoffStrategy is assigned.
     * This ensures the same behavior as if the `RabbitMqDlxDelayStrategy` was assigned.
     *
     * You can assign different backoffStrategies with extra options, for example:
     *  - \VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools\ConstantBackoffStrategy::class
     *  - \VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools\LinearBackoffStrategy::class
     *  - \VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools\ExponentialBackoffStrategy::class
     *  - \VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools\PolynomialBackoffStrategy::class
     *
     * The options must be an array of key -> value.
     *
     * For reference about RabbitMQ backoff strategy, see the following article:
     * https://m.alphasights.com/exponential-backoff-with-rabbitmq-78386b9bec81
     *
     * ### First-in First-out concept
     *
     * U can easily prioritize delayed messages. When set to `true` a message will be set with a higher priority.
     * This means that delayed messages are handled first when returning to the queue.
     *
     * This is useful when your queue has allot of jobs, and you want to make sure, a job will be handled
     * as soon as possible. This way RabbitMq handles the jobs and the way they are consumed by workers.
     *
     */
    'delay' => [
        'strategy' => env('RABBITMQ_DELAY_STRATEGY', \Enqueue\AmqpTools\RabbitMqDlxDelayStrategy::class),
        'backoff'  => [
            'strategy' => env('RABBITMQ_DELAY_BACKOFF_STRATEGY', \VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools\ConstantBackoffStrategy::class),
            'options'  => [],
        ],
        'prioritize'=> env('RABBITMQ_DELAY_PRIORITIZE'),
    ],

];
