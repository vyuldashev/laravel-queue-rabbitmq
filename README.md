RabbitMQ Queue driver for Laravel
======================
[![Latest Stable Version](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/v/stable?format=flat-square)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq)
[![Build Status](https://img.shields.io/travis/vyuldashev/laravel-queue-rabbitmq.svg?style=flat-square)](https://travis-ci.org/vyuldashev/laravel-queue-rabbitmq)
[![Total Downloads](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/downloads?format=flat-square)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq)
[![StyleCI](https://styleci.io/repos/14976752/shield)](https://styleci.io/repos/14976752)
[![License](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/license?format=flat-square)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq)

## Support Policy

Only the latest version will get new features. Bug fixes will be provided using the following scheme:

| Package Version | Laravel Version | Bug Fixes Until     |                                                                                             |
|-----------------|-----------------|---------------------|---------------------------------------------------------------------------------------------|
| 6.0             | 5.5             | August 30th, 2019   | [Documentation](https://github.com/vyuldashev/laravel-queue-rabbitmq/blob/v6.0/README.md)   |
| 7.0             | 5.6             | August 7th, 2018    | [Documentation](https://github.com/vyuldashev/laravel-queue-rabbitmq/blob/v7.0/README.md)   |
| 7.1             | 5.7             | March 4th, 2019     | [Documentation](https://github.com/vyuldashev/laravel-queue-rabbitmq/blob/v7.0/README.md)   |
| 7.2             | 5.8             | August 26th, 2019   | [Documentation](https://github.com/vyuldashev/laravel-queue-rabbitmq/blob/v7.0/README.md)   |
| 8.0             | 5.8             | August 26th, 2019   | [Documentation](https://github.com/vyuldashev/laravel-queue-rabbitmq/blob/v8.0/README.md)   |
| 9               | 6               | September 3rd, 2021 | [Documentation](https://github.com/vyuldashev/laravel-queue-rabbitmq/blob/v9.0/README.md)   |
| 10              | 6, 7            | September 3rd, 2021 | [Documentation](https://github.com/vyuldashev/laravel-queue-rabbitmq/blob/master/README.md) |

## Installation

You can install this package via composer using this command:

```
composer require vladimir-yuldashev/laravel-queue-rabbitmq
```

The package will automatically register itself.

Add connection to `config/queue.php`:

```php
'connections' => [
    // ...

    'rabbitmq' => [
    
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
        
    ],

    // ...    
],
```

### Optional Config

Optionally add queue options to the config of a connection. 
Every queue created for this connection, get's the properties.

When you want to prioritize messages when they were delayed, then this is possible by adding extra options.
- When max-priority is omitted, the max priority is set with 2 when used.

```php
'connections' => [
    // ...

    'rabbitmq' => [
        // ...

        'options' => [
            'queue' => [
                // ...

                'prioritize_delayed_messages' =>  false,
                'queue_max_priority' => 10,
            ],
        ],
    ],

    // ...    
],
```

When you want to publish messages against an exchange with routing-key's, then this is possible by adding extra options.
- When the exchange is omitted, RabbitMQ will use the `amq.direct` exchange for the routing-key
- When routing-key is omitted the routing-key by default is the `queue` name.
- When using `%s` in the routing-key the queue_name will be substituted.

> Note: when using exchange with routing-key, u probably create your queues with bindings yourself.
  
```php
'connections' => [
    // ...

    'rabbitmq' => [
        // ...

        'options' => [
            'queue' => [
                // ...

                'exchange' => 'application-x',
                'exchange_type' => 'topic',
                'exchange_routing_key' => '',
            ],
        ],
    ],

    // ...    
],
```

In Laravel failed jobs are stored into the database. But maybe you want to instruct some other process to also do something with the message.
When you want to instruct RabbitMQ to reroute failed messages to a exchange or a specific queue, then this is possible by adding extra options.
- When the exchange is omitted, RabbitMQ will use the `amq.direct` exchange for the routing-key
- When routing-key is omitted, the routing-key by default the `queue` name is substituted with `'.failed'`.
- When using `%s` in the routing-key the queue_name will be substituted.

> Note: When using failed_job exchange with routing-key, u probably need to create your exchange/queue with bindings yourself.
  
```php
'connections' => [
    // ...

    'rabbitmq' => [
        // ...

        'options' => [
            'queue' => [
                // ...

                'reroute_failed' => true,
                'failed_exchange' => 'failed-exchange',
                'failed_routing_key' => 'application-x.%s',
            ],
        ],
    ],

    // ...    
],
```

## Laravel Usage

Once you completed the configuration you can use Laravel Queue API. If you used other queue drivers you do not need to change anything else. If you do not know how to use Queue API, please refer to the official Laravel documentation: http://laravel.com/docs/queues

## Laravel Horizon Usage

Starting with 8.0, this package supports [Laravel Horizon](http://horizon.laravel.com) out of the box. Firstly, install Horizon and then set `RABBITMQ_WORKER` to `horizon`.

## Lumen Usage

For Lumen usage the service provider should be registered manually as follow in `bootstrap/app.php`:

```php
$app->register(VladimirYuldashev\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider::class);
```

## Consuming Messages

There are two ways of consuming messages. 

1. `queue:work` command which is Laravel's built-in command. This command utilizes `basic_get`.

2. `rabbitmq:consume` command which is provided by this package. This command utilizes `basic_consume` and is more performant than `basic_get` by ~2x.

## Testing

Setup RabbitMQ using `docker-compose`:

```bash
docker-compose up -d rabbitmq
```

To run the test suite you can use the following commands:

```bash
# To run both style and unit tests.
composer test

# To run only style tests.
composer test:style

# To run only unit tests.
composer test:unit
```

If you receive any errors from the style tests, you can automatically fix most,
if not all of the issues with the following command:

```bash
composer fix:style
```

## Contribution

You can contribute to this package by discovering bugs and opening issues. Please, add to which version of package you create pull request or issue. (e.g. [5.2] Fatal error on delayed job)
