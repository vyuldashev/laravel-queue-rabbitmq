# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v10.2.1...master)

## [10.2.1 (2020-05-11)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v10.2.0...v10.2.1)

- Fix: When a job fails it tries to reject it twice [#322](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/322)

## [10.2.0 (2020-04-24)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v10.1.3...v10.2.0)

Huge thanks to [adm-bone](https://github.com/adm-bome) for this release.

- Added support for Laravel 7.0 [#319](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/319)
- Added `rabbitmq:exchange-delete` artisan command [#317](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/317)
- Added `rabbitmq:queue-delete` artisan command [#317](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/317)
- Failed jobs can be rerouted to another exchange [#317](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/317)
- Exchange type is configurable [#317](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/317)
- Job attempts are fixed [#304](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/304)
- Added prioritization for failed jobs [#304](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/304)
- Fixed: if delay is not set when releasing a job, job will be lost [#304](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/304)
- Fix loosing messages when forced to close connection [#311](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/311)
- Fixed unacked message when class not found [#314](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/314)

## [10.1.3 (2020-01-12)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v10.1.2...v10.1.3)

- Fix 100% CPU usage of `rabbitmq:consume` command by adding sleep to consumer when no messages are got from the queue.
- Fix `stop-on-empty` flag for `rabbitmq:consume` command.

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
