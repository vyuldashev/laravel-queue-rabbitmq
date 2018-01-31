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

The package will automatically register itself.

Add these properties to `.env` with proper values:

```
QUEUE_DRIVER=rabbitmq
RABBITMQ_QUEUE=queue_name

RABBITMQ_DSN=amqp: # same as amqp://guest:guest@127.0.0.1:5672/%2F
# or 
RABBITMQ_HOST=127.0.0.1
RABBITMQ_PORT=5672
RABBITMQ_VHOST=/
RABBITMQ_LOGIN=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_QUEUE=queue_name
```

Optionally, if you want to to use an SSL connection, add these properties to the `.env` with proper values:
```
RABBITMQ_SSL=true
RABBITMQ_SSL_CAFILE=/path_to_your_ca_file
RABBITMQ_SSL_LOCALCERT=
RABBITMQ_SSL_PASSPHRASE=
RABBITMQ_SSL_KEY=
```

Using an SSL connection will also require to configure your RabbitMQ to enable SSL. More details can be founds here: https://www.rabbitmq.com/ssl.html

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
...
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
