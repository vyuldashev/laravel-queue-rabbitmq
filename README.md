RabbitMQ driver for Laravel
======================
[![Latest Stable Version](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/v/stable)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq) [![Total Downloads](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/downloads)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq) [![Latest Unstable Version](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/v/unstable)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq) [![License](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/license)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq)

####Installation

Require this package in your composer.json and run composer update (IMPORTANT! DO NOT USE "dev-master"):

	"vladimir-yuldashev/laravel-queue-rabbitmq": "4.2"

And run:

	composer update

After composer update is finished you need to add ServiceProvider to your `providers` array in app.php:
				
   
	'VladimirYuldashev\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider',


now you are able to configure your connections in queue.php:

	return [
	
		'default'     => 'rabbitmq',
	
		'connections' => [
	
			'rabbitmq' => [
				'driver'          => 'rabbitmq',
	
				'host'            => '',
				'port'            => 5672,
	
				'vhost'           => '/',
				'login'           => '',
				'password'        => '',
	
				'queue'           => '', // name of the default queue,
	
				'queue_params'    => [
					'passive'     => false,
					'durable'     => true,
					'exclusive'   => false,
					'auto_delete' => false,
				],
	
				'exchange_params' => [
					'type'        => 'direct', // more info at http://www.rabbitmq.com/tutorials/amqp-concepts.html
					'passive'     => false,
					'durable'     => true, // the exchange will survive server restarts
					'auto_delete' => false, // the exchange won't be deleted once the channel is closed.
				],
	
			],
	
		],
	
	];

You can also find these examples in src/examples folder. 

####Usage
Once you completed the configuration you can use Laravel Queue API. If you used other queue drivers you do not need to change anything else. If you do not know how to use Queue API, please refer to the official Laravel documentation: http://laravel.com/docs/queues

####PHPUnit
Unit tests will be provided soon.

####Contribution
You can contribute to this package by discovering buys and opening issues. Enjoy!