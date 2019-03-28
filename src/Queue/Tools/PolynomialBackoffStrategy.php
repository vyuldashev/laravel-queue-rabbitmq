<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools;

class PolynomialBackoffStrategy extends AbstractBackoffStrategy
{
    /**
     * Delay in milliseconds.
     *
     * @param int $delay
     * @param int $attempt
     * @return int
     */
    public function backoffDelayTime(int $delay, int $attempt): int
    {
        return intval(pow($attempt, $this->options->get('factor', 2)) * $delay);
    }
}
