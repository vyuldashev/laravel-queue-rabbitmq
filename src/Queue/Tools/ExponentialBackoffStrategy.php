<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools;

class ExponentialBackoffStrategy extends AbstractBackoffStrategy
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
        if (1 === $attempt) {
            return $delay;
        }

        return pow(2, $attempt) * $delay;
    }
}
