# Changelog

All notable changes to this project will be documented in this file.

## [10.1.2 (2019-12-24)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v10.1.1...v10.1.2)

- Fix `rabbitmq:queue-bind` command. [#294](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/294)

## [10.1.1 (2019-12-18)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v10.1.0...v10.1.1)

- Fix `rabbitmq:exchange-declare` command. [#293](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/293)

## [10.1.0 (2019-12-16)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v10.0.2...v10.1.0)

- Add `rabbitmq:consume` command which uses `basic_consume` instead of `basic_get` used by `queue:work`. [#289](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/289)
- Heartbeat disabled globally
- Shuffle hosts before connecting to get better load balancing

## [10.0.2 (2019-12-13)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v10.0.1...v10.0.2)

- Finally fix [#235](https://github.com/vyuldashev/laravel-queue-rabbitmq/issues/235)

## [10.0.1 (2019-12-13)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v10.0.0...v10.0.1)

- Add missing container instance and connectionName to RabbitMQJob

## [10.0.0 (2019-12-12)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v9.0...v10.0.0)

- Switch from enqueue to [php-amqplib](https://github.com/php-amqplib/php-amqplib)
- Fix [#235](https://github.com/vyuldashev/laravel-queue-rabbitmq/issues/235)
- Add support for multiple hosts
- Added `exchange:declare` artisan command
- Added `queue:bind` artisan command
- Added `queue:declare` artisan command
- Added `queue:purge` artisan command
- Bulk push messages using `batch_basic_publish`
- No more “sleeps”. Exception will be thrown on lost connection or if any other exception occurs and process manager should be configured properly to manage such situations.
