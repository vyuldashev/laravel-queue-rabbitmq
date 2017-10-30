<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Queue\Events\WorkerStopping;
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
            'ssl_on' => $config['ssl_params']['ssl_on'],
            'ssl_verify' => $config['ssl_params']['verify_peer'],
            'ssl_cacert' => $config['ssl_params']['cafile'],
            'ssl_cert' => $config['ssl_params']['local_cert'],

            // TODO 'ssl_key' not supported
            // TODO: add passphrase
        ]);

        $factory->setDelayStrategy(new RabbitMqDlxDelayStrategy());
        $context = $factory->createContext();

        \Event::listen(WorkerStopping::class, function () use($context) {
            $context->close();
        });

        return new RabbitMQQueue($context, $config);
    }
}
