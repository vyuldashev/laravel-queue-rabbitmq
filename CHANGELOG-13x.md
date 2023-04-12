# Changelog

All notable changes to this project will be documented in this file.

## [unreleased](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v13.2.0...master)

## [13.3.0](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v13.2.0...13.3.0)

- Refactor the creation of RabbitMQ Connection and QueueAPI. [#528](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/528)
- Added configuration object as single dependency for RabbitMQQueue in constructor. [#528](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/528)
- Fix method getExchangeType, not throwing an exception. [#528](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/528)
- Separating the apilogic from the actual publishing to RabbitMQ. [#528](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/528)
- Added a reconnect method. [#528](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/528)
- Fix the connection and channel not being fully lazy, when QueueAPI was created. [#528](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/528)
- Keep track of declared queue's within RabbitMQ. [#528](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/528)
- Implemented the 'rest' option to the consumer [#530](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/530)

## [13.2.0](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v13.1.0...13.2.0)

- Compatibility with Laravel 10 [#525](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/525)

## [13.1.0 (2023-01-25)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v13.0.1...v13.1.0)

- Fix delay parameter not being used [#502](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/502)
- Resolve Laravel 9 incompatabilities [#502](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/502)
- Fix Horizon invalid delay property [#502](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/502)

## [13.0.1 (2022-09-16)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v13.0.0...v13.0.1)

- Add $dispatchAfterCommit when running via Horizon [#484](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/484)

## [13.0.0 (2022-09-15)](https://github.com/vyuldashev/laravel-queue-rabbitmq/compare/v12.0.1...v13.0.0)

- Dispatch a job after DB transaction commit [#468](https://github.com/vyuldashev/laravel-queue-rabbitmq/pull/468)
