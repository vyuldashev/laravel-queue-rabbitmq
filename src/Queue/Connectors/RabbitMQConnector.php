<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors;

use Illuminate\Contracts\Queue\Queue;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQConnector implements RabbitMQConnectorInterface
{
    /** @var AMQPStreamConnection */
    private $connection;

    private $config;

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
        $this->connection = new AMQPStreamConnection(
            $config['host'],
            $config['port'],
            $config['login'],
            $config['password'],
            $config['vhost']
        );

        return new RabbitMQQueue(
            $this,
            $config
        );
    }

    public function connection()
    {
        return $this->connection;
    }

    public function reconnect()
    {
        $this->connection->close();
        $this->connection->reconnect();
    }
}
