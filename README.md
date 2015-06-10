RabbitMQ Queue driver for Laravel
======================

####Installation

Require this package in your composer.json and run composer update (IMPORTANT! DO NOT USE "dev-master"):

	"fintech-fab/laravel-queue-rabbitmq": "5.1"
    
After composer update is finished you need to add ServiceProvider to your `providers` array in `app.php`:
				
	FintechFab\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider::class,

Add these lines to your `app/config/queue.php` file to `connections` array:
   
	'rabbitmq' => [
		'driver'          => 'rabbitmq',

		'host'            => env('RABBITMQ_HOST', '127.0.0.1'),
		'port'            => env('RABBITMQ_PORT', 5672),

		'vhost'           => env('RABBITMQ_VHOST', '/'),
		'login'           => env('RABBITMQ_LOGIN', 'guest'),
		'password'        => env('RABBITMQ_PASSWORD', 'guest'),

		'queue'           => env('RABBITMQ_QUEUE'), // name of the default queue,

		'queue_params'    => [
			'passive'     => env('RABBITMQ_QUEUE_PASSIVE', false),
			'durable'     => env('RABBITMQ_QUEUE_DURABLE', true),
			'exclusive'   => env('RABBITMQ_QUEUE_EXCLUSIVE', false),
			'auto_delete' => env('RABBITMQ_QUEUE_AUTODELETE', false),
		],

		'exchange_params' => [
			'type'        => env('RABBITMQ_EXCHANGE_TYPE', 'direct'), // more info at http://www.rabbitmq.com/tutorials/amqp-concepts.html
			'passive'     => env('RABBITMQ_EXCHANGE_PASSIVE', false),
			'durable'     => env('RABBITMQ_EXCHANGE_DURABLE', true), // the exchange will survive server restarts
			'auto_delete' => env('RABBITMQ_EXCHANGE_AUTODELETE', false),
		],

	],
		
And add these properties to `.env` with proper values: 

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
4.0, 4.1, 4.2, 5.0, 5.1
The version is being matched by the release tag of this library.
