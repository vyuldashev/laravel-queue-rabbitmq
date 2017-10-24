<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors;

use PhpAmqpLib\Connection\AMQPSSLConnection;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQConnectorSSL implements RabbitMQConnectorInterface
{
    /** @var AMQPSSLConnection */
    private $connection;

    private $config;

    /**
     * Establish a queue connection.
     *
     * @param array $config
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        // Remove null values from the SSL config
        foreach ($config['ssl_params'] as $idx => $option) {
            if ($option === null || empty($option)) {
                unset($config['ssl_params'][$idx]);
            }
        }

        $this->config = $config;

        // // Create connection with AMQP
        // $this->connection = new AMQPSSLConnection(
        //     $this->config['host'],
        //     $this->config['port'],
        //     $this->config['login'],
        //     $this->config['password'],
        //     $this->config['vhost'],
        //     $this->config['ssl_params']
        // );

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
        // Create connection with AMQP
        $this->connection = new AMQPSSLConnection(
            $this->config['host'],
            $this->config['port'],
            $this->config['login'],
            $this->config['password'],
            $this->config['vhost'],
            $this->config['ssl_params']
        );
    }
}
