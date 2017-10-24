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
        $this->config = $config;
        $this->createConnection();
        
        return new RabbitMQQueue(
            $this->connection,
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
        $this->createConnection();
    }

    private function createConnection()
    {
        // create connection with AMQP
        $this->connection = new AMQPStreamConnection(
            $this->config['host'],
            $this->config['port'],
            $this->config['login'],
            $this->config['password'],
            $this->config['vhost']
        );
    }
}
