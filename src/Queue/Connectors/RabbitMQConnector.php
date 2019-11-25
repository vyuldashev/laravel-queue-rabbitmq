<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors;

use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use VladimirYuldashev\LaravelQueueRabbitMQ\Horizon\Listeners\RabbitMQFailedEvent;
use VladimirYuldashev\LaravelQueueRabbitMQ\Horizon\RabbitMQQueue as HorizonRabbitMQQueue;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQConnector implements ConnectorInterface
{
    /**
     * @var Dispatcher
     */
    private $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Establish a queue connection.
     *
     * @param array $config
     *
     * @return RabbitMQQueue
     * @throws Exception
     */
    public function connect(array $config): Queue
    {
        $connection = $this->createConnection($config);
        $channel = $connection->channel();

        $this->dispatcher->listen(WorkerStopping::class, static function () use ($channel, $connection): void {
            $channel->close();
            $connection->close();
        });

        $worker = Arr::get($config, 'worker', 'default');

        if ($worker === 'default') {
            return new RabbitMQQueue($connection, $channel, $config['queue']);
        }

        if ($worker === 'horizon') {
            $this->dispatcher->listen(JobFailed::class, RabbitMQFailedEvent::class);

            return new HorizonRabbitMQQueue($connection, $channel, $config['queue']);
        }

        if ($worker instanceof RabbitMQQueue) {
            return new $worker($connection, $channel, $config);
        }

        throw new InvalidArgumentException('Invalid worker.');
    }

    /**
     * @param array $config
     * @return AbstractConnection
     * @throws Exception
     */
    protected function createConnection(array $config): AbstractConnection
    {
        /** @var AbstractConnection $connection */
        $connection = Arr::get($config, 'connection', AMQPLazyConnection::class);

        return $connection::create_connection(
            Arr::get($config, 'hosts', []),
            Arr::get($config, 'options', [])
        );
    }
}
