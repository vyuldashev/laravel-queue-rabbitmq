<?php
/**
 * This is an example configuration of queue.php for RabbitMQ driver
 *
 * you can add as many connections as you want
 */

return [

	'default'     => 'rabbitmq',

	'connections' => [

		'rabbitmq' => [
			'driver'         => 'rabbitmq',

			'host'           => '',
			'port'           => '',

			'vhost'          => '',
			'login'          => '',
			'password'       => '',

			'queue'          => '', // name of the default queue

			'exchange_name'  => '', // name of the exchange

			// Type of your exchange
			// Can be AMQP_EX_TYPE_DIRECT or AMQP_EX_TYPE_FANOUT
			// see documentation for more info
			// http://www.rabbitmq.com/tutorials/amqp-concepts.html
			'exchange_type'  => AMQP_EX_TYPE_DIRECT,
			'exchange_flags' => AMQP_DURABLE,


		],

	],

];
