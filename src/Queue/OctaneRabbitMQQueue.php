<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue;

use PhpAmqpLib\Exception\AMQPChannelClosedException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;

class OctaneRabbitMQQueue extends RabbitMQQueue
{
    /**
     * {@inheritdoc}
     *
     * @throws AMQPProtocolChannelException
     */
    public function size($queue = null): int
    {
        return $this->withReconnectHandler(fn () => parent::size($queue));
    }

    /**
     * {@inheritdoc}
     *
     * @throws AMQPProtocolChannelException
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $this->withReconnectHandler(fn () => parent::pushRaw($payload, $queue, $options));
    }

    /**
     * @param $delay
     * @param $payload
     * @param  null  $queue
     * @param  int  $attempts
     * @return mixed
     *
     * @throws AMQPProtocolChannelException
     */
    public function laterRaw($delay, $payload, $queue = null, $attempts = 0)
    {
        return $this->withReconnectHandler(parent::laterRaw($delay, $payload, $queue, $attempts));
    }

    /**
     * {@inheritdoc}
     *
     * @throws AMQPProtocolChannelException
     */
    public function bulk($jobs, $data = '', $queue = null): void
    {
        $this->withReconnectHandler(parent::bulk($jobs, $data, $queue));
    }

    /**
     * @param  string  $payload
     * @param  null  $queue
     * @param  array  $options
     * @return mixed
     *
     * @throws AMQPProtocolChannelException
     */
    public function bulkRaw(string $payload, $queue = null, array $options = [])
    {
        return $this->withReconnectHandler(parent::bulkRaw($payload, $queue, $options));
    }

    /**
     * Checks if the given exchange already present/defined in RabbitMQ.
     * Returns false when when the exchange is missing.
     *
     * @param  string  $exchange
     * @return bool
     *
     * @throws AMQPProtocolChannelException
     */
    public function isExchangeExists(string $exchange): bool
    {
        return $this->withReconnectHandler(fn () => parent::isExchangeExists($exchange));
    }

    /**
     * Declare a exchange in rabbitMQ, when not already declared.
     *
     * @param  string  $name
     * @param  string  $type
     * @param  bool  $durable
     * @param  bool  $autoDelete
     * @param  array  $arguments
     * @return void
     */
    public function declareExchange(
        string $name,
        string $type = AMQPExchangeType::DIRECT,
        bool $durable = true,
        bool $autoDelete = false,
        array $arguments = []
    ): void {
        $this->withReconnectHandler(fn() => parent::declareExchange($name, $type, $durable, $autoDelete, $arguments));
    }

    /**
     * Delete a exchange from rabbitMQ, only when present in RabbitMQ.
     *
     * @param  string  $name
     * @param  bool  $unused
     * @return void
     *
     * @throws AMQPProtocolChannelException
     */
    public function deleteExchange(string $name, bool $unused = false): void
    {
        $this->withReconnectHandler(fn () => parent::deleteExchange($name, $unused));
    }

    /**
     * Checks if the given queue already present/defined in RabbitMQ.
     * Returns false when when the queue is missing.
     *
     * @param  string|null  $name
     * @return bool
     *
     * @throws AMQPProtocolChannelException
     */
    public function isQueueExists(string $name = null): bool
    {
        return $this->withReconnectHandler(fn () => parent::isQueueExists($name));
    }

    /**
     * Declare a queue in rabbitMQ, when not already declared.
     *
     * @param  string  $name
     * @param  bool  $durable
     * @param  bool  $autoDelete
     * @param  array  $arguments
     * @return void
     */
    public function declareQueue(
        string $name,
        bool $durable = true,
        bool $autoDelete = false,
        array $arguments = []
    ): void {
        $this->withReconnectHandler(fn () => parent::declareQueue($name, $durable, $autoDelete, $arguments));
    }

    /**
     * Delete a queue from rabbitMQ, only when present in RabbitMQ.
     *
     * @param  string  $name
     * @param  bool  $if_unused
     * @param  bool  $if_empty
     * @return void
     *
     * @throws AMQPProtocolChannelException
     */
    public function deleteQueue(string $name, bool $if_unused = false, bool $if_empty = false): void
    {
        $this->withReconnectHandler(fn () => parent::deleteQueue($name, $if_unused, $if_empty));
    }

    /**
     * Bind a queue to an exchange.
     *
     * @param  string  $queue
     * @param  string  $exchange
     * @param  string  $routingKey
     * @return void
     */
    public function bindQueue(string $queue, string $exchange, string $routingKey = ''): void
    {
        $this->withReconnectHandler(fn () => parent::bindQueue($queue, $exchange, $routingKey));
    }

    /**
     * Purge the queue of messages.
     *
     * @param  string|null  $queue
     * @return void
     */
    public function purge(string $queue = null): void
    {
        $this->withReconnectHandler(fn () => parent::purge($queue));
    }

    /**
     * Acknowledge the message.
     *
     * @param  RabbitMQJob  $job
     * @return void
     */
    public function ack(RabbitMQJob $job): void
    {
        $this->withReconnectHandler(fn () => parent::ack($job));
    }

    /**
     * Reject the message.
     *
     * @param  RabbitMQJob  $job
     * @param  bool  $requeue
     * @return void
     */
    public function reject(RabbitMQJob $job, bool $requeue = false): void
    {
        $this->withReconnectHandler(fn () => parent::reject($job, $requeue));
    }

    public function reconnect()
    {
        $this->connection->reconnect();
        $this->channel = $this->connection->channel();
    }

    public function withReconnectHandler($callback)
    {
        try {
            return $callback();
        } catch (AMQPConnectionClosedException|AMQPChannelClosedException|AMQPProtocolChannelException $e) {
            $this->reconnect();
            return $callback();
        }
    }
}
