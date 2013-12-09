<?php
/**
 * This is an example configuration of queue.php for RabbitMQ driver
 *
 * you can add as many connections as you want
 */

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
