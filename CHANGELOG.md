# Changelog

All notable changes to this project will be documented in this file.

## [10.0.0 (2019-xx-xx)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v9.0...master)

- Switch from enqueue to [php-amqplib](https://github.com/php-amqplib/php-amqplib)
- Fix #235
- Add support for multiple hosts
- Added `exchange:declare` artisan command
- Added `queue:bind` artisan command
- Added `queue:declare` artisan command
- Added `queue:purge` artisan command
- Bulk push messages using `batch_basic_publish`
- No more “sleeps”. Exception will be thrown on lost connection or if any other exception occurs and process manager should be configured properly to manage such situations.
