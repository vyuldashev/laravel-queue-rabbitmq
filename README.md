RabbitMQ Queue driver for Laravel
======================
[![Latest Stable Version](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/v/stable?format=flat-square)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq)
[![Build Status](https://img.shields.io/travis/vladimir-yuldashev/laravel-queue-rabbitmq.svg?style=flat-square)](https://travis-ci.org/vladimir-yuldashev/laravel-queue-rabbitmq)
[![Total Downloads](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/downloads?format=flat-square)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq)
[![StyleCI](https://styleci.io/repos/14976752/shield)](https://styleci.io/repos/14976752)
[![License](https://poser.pugx.org/vladimir-yuldashev/laravel-queue-rabbitmq/license?format=flat-square)](https://packagist.org/packages/vladimir-yuldashev/laravel-queue-rabbitmq)

####Installation

1. Install this package via composer using:

	`composer require vladimir-yuldashev/laravel-queue-rabbitmq:5.4`

2. Add LaravelQueueRabbitMQServiceProvider to `providers` array in `config/app.php`:

	`VladimirYuldashev\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider::class,`

3. Add these properties to `.env` with proper values:

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
You can contribute to this package by discovering bugs and opening issues. Please, add to which version of package you create pull request or issue. (e.g. [5.2] Fatal error on delayed job)

####Supported versions of Laravel (+Lumen)
`4.0, 4.1, 4.2, 5.0, 5.1, 5.2, 5.3, 5.4`

The version is being matched by the release tag of this library.
