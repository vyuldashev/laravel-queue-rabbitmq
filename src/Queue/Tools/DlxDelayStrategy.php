<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools;

use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpDestination;
use Enqueue\AmqpTools\DelayStrategy;
use Interop\Queue\Exception\InvalidDestinationException;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;

class DlxDelayStrategy implements DelayStrategy, BackoffStrategyAware, PrioritizeAware
{
    use BackoffStrategyAwareTrait, PrioritizeAwareTrait;

    /**
     * Delay message.
     *
     * @param AmqpContext $context
     * @param AmqpDestination $dest
     * @param AmqpMessage $message
     * @param int $delay
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     */
    public function delayMessage(AmqpContext $context, AmqpDestination $dest, AmqpMessage $message, int $delay): void
    {
        $delayedAttempt = intval($message->getProperty(RabbitMQJob::ATTEMPT_COUNT_HEADERS_KEY, 2));
        $previousAttempt = $delayedAttempt - 1;

        $delay = $this->calculateDelay($delay, $previousAttempt);

        $delayMessage = $this->createDelayMessage($context, $message);
        $delayQueue = $this->createDelayQueue($context, $dest, $message, $delayMessage, $delay);
        $producer = $this->createProducer($context, $previousAttempt);

        $producer->send($delayQueue, $delayMessage);
    }

    /**
     * @param AmqpContext $context
     * @param AmqpDestination $dest
     * @param AmqpMessage $message
     * @param AmqpMessage $delayMessage
     * @param int $delay
     * @return AmqpQueue
     * @throws InvalidDestinationException
     */
    protected function createDelayQueue(AmqpContext $context, AmqpDestination $dest, AmqpMessage $message, AmqpMessage $delayMessage, int $delay): AmqpQueue
    {
        if ($dest instanceof AmqpTopic) {
            $routingKey = $message->getRoutingKey() ? '.'.$message->getRoutingKey() : '';
            $name = sprintf('enqueue.%s%s.%s.x.delay', $dest->getTopicName(), $routingKey, $delay);

            $delayQueue = $context->createQueue($name);
            $delayQueue->addFlag(AmqpTopic::FLAG_DURABLE);
            $delayQueue->setArgument('x-message-ttl', $delay);
            $delayQueue->setArgument('x-expires', $delay * 2);
            $delayQueue->setArgument('x-dead-letter-exchange', $dest->getTopicName());
            $delayQueue->setArgument('x-dead-letter-routing-key', (string) $delayMessage->getRoutingKey());
        } elseif ($dest instanceof AmqpQueue) {
            $delayQueue = $context->createQueue('enqueue.'.$dest->getQueueName().'.'.$delay.'.delayed');
            $delayQueue->addFlag(AmqpTopic::FLAG_DURABLE);
            $delayQueue->setArgument('x-message-ttl', $delay);
            $delayQueue->setArgument('x-expires', $delay * 2);
            $delayQueue->setArgument('x-dead-letter-exchange', '');
            $delayQueue->setArgument('x-dead-letter-routing-key', $dest->getQueueName());
        } else {
            throw new InvalidDestinationException(sprintf('The destination must be an instance of %s but got %s.',
                AmqpTopic::class.'|'.AmqpQueue::class,
                get_class($dest)
            ));
        }

        $context->declareQueue($delayQueue);

        return $delayQueue;
    }

    /**
     * @param int $delay
     * @param int $attempt
     * @return int
     */
    private function calculateDelay(int $delay, int $attempt): int
    {
        if ($this->backoffStrategy) {
            $delay = $this->backoffStrategy->backoffDelayTime($delay, $attempt);
        }

        return $delay;
    }

    /**
     * @param AmqpContext $context
     * @param int $priority
     * @return \Interop\Amqp\AmqpProducer
     * @throws \Interop\Queue\Exception\PriorityNotSupportedException
     */
    private function createProducer(AmqpContext $context, int $priority = null): \Interop\Amqp\AmqpProducer
    {
        $producer = $context->createProducer();

        if ($this->prioritize && $priority) {
            $producer->setPriority($priority);
        }

        return $producer;
    }

    /**
     * @param AmqpContext $context
     * @param AmqpMessage $message
     * @return AmqpMessage
     */
    private function createDelayMessage(AmqpContext $context, AmqpMessage $message): AmqpMessage
    {
        $properties = $message->getProperties();

        // The x-death header must be removed because of the bug in RabbitMQ.
        // It was reported that the bug is fixed since 3.5.4 but I tried with 3.6.1 and the bug still there.
        // https://github.com/rabbitmq/rabbitmq-server/issues/216
        unset($properties['x-death']);

        $delayMessage = $context->createMessage($message->getBody(), $properties, $message->getHeaders());
        $delayMessage->setRoutingKey($message->getRoutingKey());

        return $delayMessage;
    }
}
