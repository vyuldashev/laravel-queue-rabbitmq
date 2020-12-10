<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Events;

use PhpAmqpLib\Connection\AbstractConnection;

class ConnectionCreated
{
    /**
     * The connection instance.
     *
     * @var AbstractConnection
     */
    public $connection;

    /**
     * The available connection tags.
     *
     * @var array
     */
    public $tags;

    /**
     * ConnectionCreated constructor.
     *
     * @param AbstractConnection $connection
     * @param array $tags
     */
    public function __construct(AbstractConnection $connection, array $tags = [])
    {
        $this->connection = $connection;
        $this->tags = $tags;
    }
}
