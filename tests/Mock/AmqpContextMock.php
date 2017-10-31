<?php
namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mock;

use Interop\Amqp\AmqpBind;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Queue\PsrDestination;

class AmqpContextMock implements AmqpContext
{
    public function declareTopic(AmqpTopic $topic)
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function deleteTopic(AmqpTopic $topic)
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function declareQueue(AmqpQueue $queue)
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function deleteQueue(AmqpQueue $queue)
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function purgeQueue(AmqpQueue $queue)
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function bind(AmqpBind $bind)
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function unbind(AmqpBind $bind)
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function setQos($prefetchSize, $prefetchCount, $global)
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function subscribe(AmqpConsumer $consumer, callable $callback)
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function unsubscribe(AmqpConsumer $consumer)
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function consume($timeout = 0)
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function close()
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function createQueue($queueName)
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function createTemporaryQueue()
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function createProducer()
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function createConsumer(PsrDestination $destination)
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function createTopic($amqpTopic)
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function createMessage($body = '', array $properties = [], array $headers = [])
    {
        throw new \LogicException('It is not expected to be called');
    }
}
