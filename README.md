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
    
        /*
         * Set to "horizon" if you wish to use Laravel Horizon.
         */
        'worker' => env('RABBITMQ_WORKER', 'default'),
    
        'dsn' => env('RABBITMQ_DSN', null),
    
        /*
         * Could be one a class that implements \Interop\Amqp\AmqpConnectionFactory for example:
         *  - \EnqueueAmqpExt\AmqpConnectionFactory if you install enqueue/amqp-ext
         *  - \EnqueueAmqpLib\AmqpConnectionFactory if you install enqueue/amqp-lib
         *  - \EnqueueAmqpBunny\AmqpConnectionFactory if you install enqueue/amqp-bunny
         */
         
        'factory_class' => Enqueue\AmqpLib\AmqpConnectionFactory::class,
    
        'host' => env('RABBITMQ_HOST', '127.0.0.1'),
        'port' => env('RABBITMQ_PORT', 5672),
    
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'login' => env('RABBITMQ_LOGIN', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
    
        'queue' => env('RABBITMQ_QUEUE', 'default'),
    
        'options' => [
    
            'exchange' => [
    
                'name' => env('RABBITMQ_EXCHANGE_NAME'),
    
                /*
                 * Determine if exchange should be created if it does not exist.
                 */
                
                'declare' => env('RABBITMQ_EXCHANGE_DECLARE', true),
    
                /*
                 * Read more about possible values at https://www.rabbitmq.com/tutorials/amqp-concepts.html
                 */
                 
                'type' => env('RABBITMQ_EXCHANGE_TYPE', \Interop\Amqp\AmqpTopic::TYPE_DIRECT),
                'passive' => env('RABBITMQ_EXCHANGE_PASSIVE', false),
                'durable' => env('RABBITMQ_EXCHANGE_DURABLE', true),
                'auto_delete' => env('RABBITMQ_EXCHANGE_AUTODELETE', false),
                'arguments' => env('RABBITMQ_EXCHANGE_ARGUMENTS'),
            ],
    
            'queue' => [
    
                /*
                 * Determine if queue should be created if it does not exist.
                 */
                
                'declare' => env('RABBITMQ_QUEUE_DECLARE', true),
    
                /*
                 * Determine if queue should be binded to the exchange created.
                 */
                
                'bind' => env('RABBITMQ_QUEUE_DECLARE_BIND', true),
    
                /*
                 * Read more about possible values at https://www.rabbitmq.com/tutorials/amqp-concepts.html
                 */
                 
                'passive' => env('RABBITMQ_QUEUE_PASSIVE', false),
                'durable' => env('RABBITMQ_QUEUE_DURABLE', true),
                'exclusive' => env('RABBITMQ_QUEUE_EXCLUSIVE', false),
                'auto_delete' => env('RABBITMQ_QUEUE_AUTODELETE', false),
                'arguments' => env('RABBITMQ_QUEUE_ARGUMENTS'),
            ],
        ],
    
        /*
         * Determine the number of seconds to sleep if there's an error communicating with rabbitmq
         * If set to false, it'll throw an exception rather than doing the sleep for X seconds.
         */
         
        'sleep_on_error' => env('RABBITMQ_ERROR_SLEEP', 5),
    
        /*
         * Optional SSL params if an SSL connection is used
         * Using an SSL connection will also require to configure your RabbitMQ to enable SSL. More details can be founds here: https://www.rabbitmq.com/ssl.html
         */
         
        'ssl_params' => [
            'ssl_on' => env('RABBITMQ_SSL', false),
            'cafile' => env('RABBITMQ_SSL_CAFILE', null),
            'local_cert' => env('RABBITMQ_SSL_LOCALCERT', null),
            'local_key' => env('RABBITMQ_SSL_LOCALKEY', null),
            'verify_peer' => env('RABBITMQ_SSL_VERIFY_PEER', true),
            'passphrase' => env('RABBITMQ_SSL_PASSPHRASE', null),
        ],   
        
    ],
    // ...    
],
```

## Laravel Usage

Once you completed the configuration you can use Laravel Queue API. If you used other queue drivers you do not need to change anything else. If you do not know how to use Queue API, please refer to the official Laravel documentation: http://laravel.com/docs/queues

## Laravel Horizon Usage

Starting with 7.4, this package supports [Laravel Horizon](http://horizon.laravel.com) out of the box. Firstly, install Horizon and then set `RABBITMQ_WORKER` to `horizon`.

## Lumen Usage

For Lumen usage the service provider should be registered manually as follow in `bootstrap/app.php`:

```php
$app->register(VladimirYuldashev\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider::class);
```


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

Setup RabbitMQ using `docker-compose`:
```bash
docker-compose up -d
```

Run tests:

``` bash
composer test
```

## Contribution

You can contribute to this package by discovering bugs and opening issues. Please, add to which version of package you create pull request or issue. (e.g. [5.2] Fatal error on delayed job)
