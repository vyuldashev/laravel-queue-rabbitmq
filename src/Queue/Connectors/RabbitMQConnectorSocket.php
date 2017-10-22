<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors;

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQConnectorSocket implements ConnectorInterface
{
    /** @var AMQPSocketConnection */
    private $connection;

    /**
     * Establish a queue connection.
     *
     * @param array $config
     *
     * @return Queue
     */
    public function connect(array $config): Queue
    {
        // create connection with AMQP
        $this->connection = new AMQPSocketConnection(
            $config['host'],
            $config['port'],
            $config['login'],
            $config['password'],
            $config['vhost']
        );

        return new RabbitMQQueue(
            $this->connection,
            $config
        );
    }

    public function connection()
    {
        return $this->connection;
    }
}
