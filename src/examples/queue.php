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
			'driver'   => 'rabbitmq',

			'host'     => '',
			'port'  => 5672,

			'vhost' => '/',
			'login'    => '',
			'password' => '',

			'queue'    => [
				'name'        => '', // name of the default queue,
				'passive'     => false,
				'durable'     => true,
				'exclusive'   => false,
				'auto_delete' => false,
			],

			'exchange' => [
				'name'        => '', // name of the exchange
				'type'        => 'direct', // more info at http://www.rabbitmq.com/tutorials/amqp-concepts.html
				'passive'     => false,
				'durable'     => true, // the exchange will survive server restarts
				'auto_delete' => false, // the exchange won't be deleted once the channel is closed.
			],

		],

	],

];
