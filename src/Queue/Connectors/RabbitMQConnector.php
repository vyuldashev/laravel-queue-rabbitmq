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
     * @return RabbitMQQueue
     *
     * @throws Exception
     */
    public function connect(array $config): Queue
    {
        $connection = $this->createConnection(Arr::except($config, 'options.queue'));

        $queue = $this->createQueue(
            Arr::get($config, 'worker', 'default'),
            $connection,
            $config['queue'],
            Arr::get($config, 'after_commit', false),
            Arr::get($config, 'options.queue', [])
        );

        if (! $queue instanceof RabbitMQQueue) {
            throw new InvalidArgumentException('Invalid worker.');
        }

        if ($queue instanceof HorizonRabbitMQQueue) {
            $this->dispatcher->listen(JobFailed::class, RabbitMQFailedEvent::class);
        }

        $this->dispatcher->listen(WorkerStopping::class, static function () use ($queue): void {
            $queue->close();
        });

        return $queue;
    }

    /**
     * @throws Exception
     */
    protected function createConnection(array $config): AbstractConnection
    {
        /** @var AbstractConnection $connection */
        $connection = Arr::get($config, 'connection', AMQPLazyConnection::class);

        // disable heartbeat when not configured, so long-running tasks will not fail
        $config = Arr::add($config, 'options.heartbeat', 0);

        return $connection::create_connection(
            Arr::shuffle(Arr::get($config, 'hosts', [])),
            $this->filter(Arr::get($config, 'options', []))
        );
    }

    /**
     * Create a queue for the worker.
     *
     * @return HorizonRabbitMQQueue|RabbitMQQueue|Queue
     */
    protected function createQueue(
        string $worker,
        AbstractConnection $connection,
        string $queue,
        bool $dispatchAfterCommit,
        array $options = []
    ) {
        switch ($worker) {
            case 'default':
                return new RabbitMQQueue($connection, $queue, $dispatchAfterCommit, $options);
            case 'horizon':
                return new HorizonRabbitMQQueue($connection, $queue, $dispatchAfterCommit, $options);
            default:
                return new $worker($connection, $queue, $dispatchAfterCommit, $options);
        }
    }

    /**
     * Recursively filter only null values.
     */
    private function filter(array $array): array
    {
        foreach ($array as $index => &$value) {
            if (is_array($value)) {
                $value = $this->filter($value);

                continue;
            }

            // If the value is null then remove it.
            if ($value === null) {
                unset($array[$index]);

                continue;
            }
        }

        return $array;
    }
}
