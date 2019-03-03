<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mock;

use Interop\Queue\Queue;
use Interop\Queue\Topic;
use Interop\Amqp\AmqpBind;
use Interop\Queue\Message;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Queue\Consumer;
use Interop\Queue\Producer;
use Interop\Amqp\AmqpContext;
use Interop\Queue\Destination;
use Interop\Queue\SubscriptionConsumer;

class AmqpContextMock implements AmqpContext
{
    public function createSubscriptionConsumer(): SubscriptionConsumer
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function declareTopic(AmqpTopic $topic): void
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function deleteTopic(AmqpTopic $topic): void
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function declareQueue(AmqpQueue $queue): int
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function deleteQueue(AmqpQueue $queue): void
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function purgeQueue(Queue $queue): void
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function bind(AmqpBind $bind): void
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function unbind(AmqpBind $bind): void
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function setQos(int $prefetchSize, int $prefetchCount, bool $global): void
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function close(): void
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function createQueue(string $queueName): Queue
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function createTemporaryQueue(): Queue
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function createProducer(): Producer
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function createConsumer(Destination $destination): Consumer
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function createTopic(string $amqpTopic): Topic
    {
        throw new \LogicException('It is not expected to be called');
    }

    public function createMessage(string $body = '', array $properties = [], array $headers = []): Message
    {
        throw new \LogicException('It is not expected to be called');
    }
}
