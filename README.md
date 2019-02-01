RabbitMQ Queue driver for Laravel
======================
[![Latest Stable Version](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/v/stable?format=flat-square)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq)
[![Build Status](https://img.shields.io/travis/vyuldashev/laravel-queue-rabbitmq.svg?style=flat-square)](https://travis-ci.org/vyuldashev/laravel-queue-rabbitmq)
[![Total Downloads](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/downloads?format=flat-square)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq)
[![StyleCI](https://styleci.io/repos/14976752/shield)](https://styleci.io/repos/14976752)
[![License](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/license?format=flat-square)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq)

## Installation

You can install this package via composer using this command:

```
composer require vladimir-yuldashev/laravel-queue-rabbitmq
```

The package will automatically register itself using Laravel auto-discovery.

Setup connection in `config/queue.php`

```php
'connections' => [
    // ...
    'rabbitmq' => [

        'driver' => 'rabbitmq',

        'host' => env('RABBITMQ_HOST', '127.0.0.1'),
        'port' => env('RABBITMQ_PORT', 5672),

        'vhost' => env('RABBITMQ_VHOST', '/'),
        'login' => env('RABBITMQ_LOGIN', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),

        'queue' => env('RABBITMQ_QUEUE', 'default'),
    ],
    // ...
],
```

For others options, see [config/rabbitmq.php](vyuldashev/laravel-queue-rabbitmq/blob/master/config/rabbitmq.php)

### Lumen
Register the service in `bootstrap/app.php`
```php

// Register Service Providers

// ...

$app->register(VladimirYuldashev\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider::class);

// ...
```

## Usage

Once you completed the configuration you can use Laravel Queue API. If you used other queue drivers you do not need to change anything else. If you do not know how to use Queue API, please refer to the official Laravel documentation: http://laravel.com/docs/queues

## Using other AMQP transports

The package uses [enqueue/amqp-lib](https://github.com/php-enqueue/enqueue-dev/blob/master/docs/transport/amqp_lib.md) transport which is based on [php-amqplib](https://github.com/php-amqplib/php-amqplib).
There is possibility to use any [amqp interop](https://github.com/queue-interop/queue-interop#amqp-interop) compatible transport, for example `enqueue/amqp-ext` or `enqueue/amqp-bunny`.
Here's an example on how one can change the transport to `enqueue/amqp-bunny`.

First, install desired transport package:

```bash
composer require enqueue/amqp-bunny:^0.8
```

Change the factory class in `config/queue.php`:

```php
    // ...
    'connections' => [
        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'factory_class' => Enqueue\AmqpBunny\AmqpConnectionFactory::class,
        ],
    ],
```

## Testing

You can run the tests with:

``` bash
vendor/bin/phpunit
```

## Contribution

You can contribute to this package by discovering bugs and opening issues. Please, add to which version of package you create pull request or issue. (e.g. [5.2] Fatal error on delayed job)
