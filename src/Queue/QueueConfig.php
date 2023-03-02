<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue;

use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;

class QueueConfig
{
    private string $queue = 'default';

    private bool $dispatchAfterCommit = false;

    private string $abstractJob = RabbitMQJob::class;

    private bool $prioritizeDelayed = false;

    private int $queueMaxPriority = 2;

    private ?string $exchange = null;

    private string $exchangeType = 'direct';

    private string $exchangeRoutingKey = '%s';

    private bool $rerouteFailed = false;

    private ?string $failedExchange = null;

    private string $failedRoutingKey = '%s.failed';

    private bool $quorum = false;

    private array $options = [];

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function setQueue(?string $queue): QueueConfig
    {
        $this->queue = $queue ?: 'default';

        return $this;
    }

    public function isDispatchAfterCommit(): bool
    {
        return $this->dispatchAfterCommit;
    }

    public function setDispatchAfterCommit($dispatchAfterCommit): QueueConfig
    {
        $this->dispatchAfterCommit = ! empty($dispatchAfterCommit);

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(?array $options): QueueConfig
    {
        $this->options = $options ?: [];

        return $this;
    }

    public function getAbstractJob(): string
    {
        return $this->abstractJob;
    }

    public function setAbstractJob(?string $abstract): QueueConfig
    {
        $this->abstractJob = $abstract ?: RabbitMQJob::class;

        return $this;
    }

    /**
     * Returns &true;, if delayed messages should be prioritized.
     */
    public function isPrioritizeDelayed(): bool
    {
        return $this->prioritizeDelayed;
    }

    public function setPrioritizeDelayed($prioritizeDelayed): QueueConfig
    {
        $this->prioritizeDelayed = ! empty($prioritizeDelayed);

        return $this;
    }

    /**
     * Returns a integer with a default of '2' for when using prioritization on delayed messages.
     * If priority queues are desired, we recommend using between 1 and 10.
     * Using more priority layers, will consume more CPU resources and would affect runtimes.
     *
     * @see https://www.rabbitmq.com/priority.html
     */
    public function getQueueMaxPriority(): int
    {
        return $this->queueMaxPriority;
    }

    public function setQueueMaxPriority($queueMaxPriority): QueueConfig
    {
        $this->queueMaxPriority = is_numeric($queueMaxPriority) ? (int) $queueMaxPriority : 2;

        return $this;
    }

    /**
     * Get the exchange name, or &null; as default value.
     */
    public function getExchange(): ?string
    {
        return $this->exchange;
    }

    public function setExchange(?string $exchange): QueueConfig
    {
        $this->exchange = $exchange ?: null;

        return $this;
    }

    public function getExchangeType(): string
    {
        return $this->exchangeType;
    }

    public function setExchangeType(?string $exchangeType): QueueConfig
    {
        $this->exchangeType = $exchangeType ?: 'direct';

        return $this;
    }

    /**
     * @return string
     */
    public function getExchangeRoutingKey(): ?string
    {
        return $this->exchangeRoutingKey;
    }

    public function setExchangeRoutingKey(?string $exchangeRoutingKey): QueueConfig
    {
        $this->exchangeRoutingKey = $exchangeRoutingKey ?: '%s';

        return $this;
    }

    /**
     * Returns &true;, if failed messages should be rerouted.
     */
    public function isRerouteFailed(): bool
    {
        return $this->rerouteFailed;
    }

    public function setRerouteFailed($rerouteFailed): QueueConfig
    {
        $this->rerouteFailed = ! empty($rerouteFailed);

        return $this;
    }

    public function getFailedExchange(): ?string
    {
        return $this->failedExchange;
    }

    public function setFailedExchange(?string $failedExchange): QueueConfig
    {
        $this->failedExchange = $failedExchange ?: null;

        return $this;
    }

    /**
     * Get the routing-key for failed messages
     * The default routing-key is the given destination substituted by '.failed'.
     */
    public function getFailedRoutingKey(): string
    {
        return $this->failedRoutingKey;
    }

    public function setFailedRoutingKey(?string $failedRoutingKey): QueueConfig
    {
        $this->failedRoutingKey = $failedRoutingKey ?: '%s.failed';

        return $this;
    }

    public function isQuorum(): bool
    {
        return $this->quorum;
    }

    public function setQuorum($quorum): QueueConfig
    {
        $this->quorum = ! empty($quorum);

        return $this;
    }
}
