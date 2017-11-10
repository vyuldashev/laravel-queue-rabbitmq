RabbitMQ Queue driver for Laravel
======================
[![Latest Stable Version](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/v/stable?format=flat-square)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq)
[![Build Status](https://img.shields.io/travis/vyuldashev/laravel-queue-rabbitmq.svg?style=flat-square)](https://travis-ci.org/vyuldashev/laravel-queue-rabbitmq)
[![Total Downloads](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/downloads?format=flat-square)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq)
[![StyleCI](https://styleci.io/repos/14976752/shield)](https://styleci.io/repos/14976752)
[![License](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/license?format=flat-square)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq)

#### Installation

1. Install this package via composer using:

```
composer require vladimir-yuldashev/laravel-queue-rabbitmq
```

2. Add these properties to `.env` with proper values:

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

3. Optionally: if you want to to use an SSL connection, add these properties to the `.env` with proper values:
```
RABBITMQ_SSL=true
RABBITMQ_SSL_CAFILE=/path_to_your_ca_file
RABBITMQ_SSL_LOCALCERT=
RABBITMQ_SSL_PASSPHRASE=
RABBITMQ_SSL_KEY=
```

Using an SSL connection will also require to configure your RabbitMQ to enable SSL. More details can be founds here: https://www.rabbitmq.com/ssl.html

4. Other AMQP transports

The package uses [enqueue/amqp-lib](https://github.com/php-enqueue/enqueue-dev/blob/master/docs/transport/amqp_lib.md) transport which is based on [php-amqplib](https://github.com/php-amqplib/php-amqplib). 
There is possibility to use any [amqp interop](https://github.com/queue-interop/queue-interop#amqp-interop) compatible transport, for example `enqueue/amqp-ext` or `enqueue/amqp-bunny`.
Here's an example on how one can change the transport to `enqueue/amqp-bunny`.

First, install the package:

```bash
$ composer require enqueue/amqp-bunny:^0.8
```
  
and change the factory class:

```php
<?php
// config/queue.php

return [
    'connections' => [
        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'factory_class' => \Enqueue\AmqpBunny\AmqpConnectionFactory::class,
        ],
    ],
];
```

#### Usage

Once you completed the configuration you can use Laravel Queue API. If you used other queue drivers you do not need to change anything else. If you do not know how to use Queue API, please refer to the official Laravel documentation: http://laravel.com/docs/queues

#### Testing

Run the tests with:

``` bash
vendor/bin/phpunit
```


#### Contribution

You can contribute to this package by discovering bugs and opening issues. Please, add to which version of package you create pull request or issue. (e.g. [5.2] Fatal error on delayed job)

> If you want to make feature for several versions (for example: 5.2, 5.3, 5.4 and 5.5). Create PR for the lowest version (5.2). Hence, you should use branch v5.2.

#### Supported versions of Laravel (+Lumen)

`4.0, 4.1, 4.2, 5.0, 5.1, 5.2, 5.3, 5.4, 5.5`

The version is being matched by the release tag of this library.
