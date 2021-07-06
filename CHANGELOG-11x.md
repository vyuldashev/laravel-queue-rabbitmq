# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v11.3.0...master)

## [11.3.0 (2021-07-06)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v11.2.0...v11.3.0)

- Quorum queues support [#359](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/359)
- max-priority support [#422](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/422)
- Ability to specify exchange and exchange_type when using pushRaw() [#420](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/420)
-  Remember exchanges once they have been verified [#407](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/407)

## [11.2.0 (2021-03-16)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v11.1.2...v11.2.0)

- PHP 8 support
- Fix missing rest option in `php artisan rabbitmq:consume` command [#416](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/416)

## [11.1.2 (2021-03-07)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v11.1.1...v11.1.2)

- Update Consumer to stop when stopIfNecessary() returns exit code [#409](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/409)

## [11.1.1 (2020-12-07)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v11.1.0...v11.1.1)

- Fix worker is stopped by timeout when no new jobs available [#352](https://github.com/vyuldashev/laravel-queue-rabbitmq/issues/352)

## [11.1.0 (2020-12-05)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v11.0.2...v11.1.0)

- Custom job class [#370](https://github.com/vyuldashev/laravel-queue-rabbitmq/issues/370)

## [11.0.2 (2020-09-20)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v11.0.1...v11.0.2)

- Add missing options to rabbitmq:consume command [#363](https://github.com/vyuldashev/laravel-queue-rabbitmq/issues/363)

## [11.0.1 (2020-09-19)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v11.0.0...v11.0.1)

- Fix rabbitmq:consume name option does not exist [#363](https://github.com/vyuldashev/laravel-queue-rabbitmq/issues/363)
- Fix Class 'Laravel\Horizon\JobId' not found [#362](https://github.com/vyuldashev/laravel-queue-rabbitmq/issues/362)

## [11.0.0 (2020-09-09)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v10.2.2...v11.0.0)

- Laravel 8 support
- Minimum PHP version is set to 7.3 
