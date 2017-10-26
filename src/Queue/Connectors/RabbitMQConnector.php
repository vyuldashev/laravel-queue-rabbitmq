<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param array $config
     *
     * @return Queue
     */
    public function connect(array $config): Queue
    {
        $factory = new AmqpConnectionFactory([
            'host' => $config['host'],
            'port' => $config['port'],
            'user' => $config['login'],
            'pass' => $config['password'],
            'vhost' => $config['vhost'],
        ]);

        $factory->setDelayStrategy(new RabbitMqDlxDelayStrategy());

        return new RabbitMQQueue(
            $factory->createContext(),
            $config
        );
    }
}
