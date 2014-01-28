RabbitMQ driver for Laravel
======================

####Installation

Require this package in your composer.json and run composer update:

	"fintech-fab/laravel-queue-rabbitmq": "4.0"
    
or run:

	composer require "fintech-fab/laravel-queue-rabbitmq"

After composer update is finished you need to add ServiceProvider to your `providers` array in app.php:
				
   
	'FintechFab\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider',


now you are able to configure your connections in queue.php:

    return array(
    
        'default'     => 'rabbitmq',
    
        'connections' => array(
    
            'rabbitmq' => array(
                'driver'        => 'rabbitmq',
    
                'host'          => '',
                'port'          => '',
    
                'vhost'         => '',
                'login'         => '',
                'password'      => '',
    
                'queue'         => '', // name of the default queue
                'exchange_name' => '', // name of the exchange
            ),
    
        ),
    
    );

You can also find these examples in src/examples folder. 

####PHPUnit
Unit tests will be provided soon.

####Contribution
You can contribute to this package by discovering buys and opening issues. Enjoy!