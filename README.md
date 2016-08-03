RabbitMQ Queue driver for Laravel
======================
[![Latest Stable Version](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/v/stable?format=flat-square)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq) [![Total Downloads](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/downloads?format=flat-square)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq) [![Latest Unstable Version](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/v/unstable?format=flat-square)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq)
[![StyleCI](https://styleci.io/repos/7548986/shield)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq)
[![License](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/license?format=flat-square)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq)

####Installation

1. Install this package via composer using:

	composer require vladimir-yuldashev/laravel-queue-rabbitmq:5.3
    
2. Add LaravelQueueRabbitMQServiceProvider to `providers` array in `config/app.php`:
				
	VladimirYuldashev\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider::class,

3. Add these lines to `config/queue.php` file to `connections` array:
   
	'rabbitmq' => [
		'driver'          		=> 'rabbitmq',

		'host'            		=> env('RABBITMQ_HOST', '127.0.0.1'),
		'port'            		=> env('RABBITMQ_PORT', 5672),

		'vhost'           		=> env('RABBITMQ_VHOST', '/'),
		'login'           		=> env('RABBITMQ_LOGIN', 'guest'),
		'password'        		=> env('RABBITMQ_PASSWORD', 'guest'),

		'queue'           		=> env('RABBITMQ_QUEUE'), // name of the default queue,
		
		'exchange_declare' 		=> env('RABBITMQ_EXCHANGE_DECLARE', true), // create the exchange if not exists
		'queue_declare_bind' 	=> env('RABBITMQ_QUEUE_DECLARE_BIND', true), // create the queue if not exists and bind to the exchange

		'queue_params'    		=> [
			'passive'     		=> env('RABBITMQ_QUEUE_PASSIVE', false),
			'durable'     		=> env('RABBITMQ_QUEUE_DURABLE', true),
			'exclusive'   		=> env('RABBITMQ_QUEUE_EXCLUSIVE', false),
			'auto_delete' 		=> env('RABBITMQ_QUEUE_AUTODELETE', false),
		],

		'exchange_params' => [
			'name'        => env('RABBITMQ_EXCHANGE_NAME', null),
			'type'        => env('RABBITMQ_EXCHANGE_TYPE', 'direct'), // more info at http://www.rabbitmq.com/tutorials/amqp-concepts.html
			'passive'     => env('RABBITMQ_EXCHANGE_PASSIVE', false),
			'durable'     => env('RABBITMQ_EXCHANGE_DURABLE', true), // the exchange will survive server restarts
			'auto_delete' => env('RABBITMQ_EXCHANGE_AUTODELETE', false),
		],

	],
		
4. Add these properties to `.env` with proper values: 

	QUEUE_DRIVER=rabbitmq

	RABBITMQ_HOST=127.0.0.1
	RABBITMQ_PORT=5672
	RABBITMQ_VHOST=/
	RABBITMQ_LOGIN=guest
	RABBITMQ_PASSWORD=guest
	RABBITMQ_QUEUE=queue_name

You can also find full examples in src/examples folder. 

####Usage
Once you completed the configuration you can use Laravel Queue API. If you used other queue drivers you do not need to change anything else. If you do not know how to use Queue API, please refer to the official Laravel documentation: http://laravel.com/docs/queues

####PHPUnit
Unit tests will be provided soon.

####Contribution
You can contribute to this package by discovering bugs and opening issues. Enjoy!

####Supported versions of Laravel
4.0, 4.1, 4.2, 5.0, 5.1, 5.2, 5.3
The version is being matched by the release tag of this library.
