<?php namespace FintechFab\LaravelQueueRabbitMQ\Queue\Connectors;

use FintechFab\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use PhpAmqpLib\Connection\AMQPConnection;

class RabbitMQConnector implements ConnectorInterface
{

    /**
     * @var AMQPConnection
     */
    protected $connection;

    /**
     * Establish a queue connection.
     *
     * @param  array $config
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        // create connection with AMQP
        $this->connection = new AMQPConnection($config['host'], $config['port'], $config['login'], $config['password'], $config['vhost']);

        return new RabbitMQQueue(
            $this->connection,
            $config
        );
    }

    public function getConnection()
    {
        return $this->connection;
    }

}