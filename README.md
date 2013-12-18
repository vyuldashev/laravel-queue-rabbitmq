RabbitMQ driver for Laravel
======================

####Installation

Require this package in your composer.json and run composer update:

	"fintech-fab/laravel-queue-rabbitmq": "dev-master"
    
or run:

	composer require "fintech-fab/laravel-queue-rabbitmq": "dev-master"

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

####Usage
Once you completed the configuration you can use Laravel Queue API. If you used other queue drivers you do not need to change anything else. If you do not know how to use Queue API, please refer to the official Laravel documentation: http://laravel.com/docs/queues

####PHPUnit
Unit tests will be provided soon.

####Contribution
You can contribute to this package by discovering buys and opening issues. Enjoy!