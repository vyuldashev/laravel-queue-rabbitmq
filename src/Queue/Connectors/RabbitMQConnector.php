<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors;

use Illuminate\Support\Arr;
use Interop\Amqp\AmqpContext;
use InvalidArgumentException;
use Enqueue\AmqpTools\DelayStrategy;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Events\JobFailed;
use Interop\Amqp\AmqpConnectionFactory;
use Enqueue\AmqpTools\DelayStrategyAware;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\WorkerStopping;
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Illuminate\Queue\Connectors\ConnectorInterface;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;
use Interop\Amqp\AmqpConnectionFactory as InteropAmqpConnectionFactory;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools\BackoffStrategy;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools\PrioritizeAware;
use Enqueue\AmqpLib\AmqpConnectionFactory as EnqueueAmqpConnectionFactory;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools\BackoffStrategyAware;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Tools\ConstantBackoffStrategy;
use VladimirYuldashev\LaravelQueueRabbitMQ\Horizon\Listeners\RabbitMQFailedEvent;
use VladimirYuldashev\LaravelQueueRabbitMQ\Horizon\RabbitMQQueue as HorizonRabbitMQQueue;

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
     * @return Queue
     * @throws \ReflectionException
     */
    public function connect(array $config): Queue
    {
        $factoryClass = Arr::get($config, 'factory_class', EnqueueAmqpConnectionFactory::class);

        if (! class_exists($factoryClass) || ! (new \ReflectionClass($factoryClass))->implementsInterface(InteropAmqpConnectionFactory::class)) {
            throw new \LogicException(sprintf('The factory_class option has to be valid class that implements "%s"', InteropAmqpConnectionFactory::class));
        }

        /** @var AmqpConnectionFactory $factory */
        $factory = new $factoryClass([
            'dsn' => Arr::get($config, 'dsn'),
            'host' => Arr::get($config, 'host', '127.0.0.1'),
            'port' => Arr::get($config, 'port', 5672),
            'user' => Arr::get($config, 'login', 'guest'),
            'pass' => Arr::get($config, 'password', 'guest'),
            'vhost' => Arr::get($config, 'vhost', '/'),
            'ssl_on' => Arr::get($config, 'ssl_params.ssl_on', false),
            'ssl_verify' => Arr::get($config, 'ssl_params.verify_peer', true),
            'ssl_cacert' => Arr::get($config, 'ssl_params.cafile'),
            'ssl_cert' => Arr::get($config, 'ssl_params.local_cert'),
            'ssl_key' => Arr::get($config, 'ssl_params.local_key'),
            'ssl_passphrase' => Arr::get($config, 'ssl_params.passphrase'),
        ]);

        if ($factory instanceof DelayStrategyAware) {
            if (! class_exists($delayStrategyClass = Arr::get($config, 'delay.strategy')) || ! in_array(DelayStrategy::class, class_implements($delayStrategyClass))) {
                $delayStrategyClass = RabbitMqDlxDelayStrategy::class;
            }

            /** @var DelayStrategy $delayStrategy */
            $delayStrategy = new $delayStrategyClass();

            if ($delayStrategy instanceof BackoffStrategyAware) {
                if (! class_exists($backoffStrategyClass = Arr::get($config, 'delay.backoff.strategy')) || ! in_array(BackoffStrategy::class, class_implements($backoffStrategyClass))) {
                    $backoffStrategyClass = ConstantBackoffStrategy::class;
                }

                /** @var BackoffStrategy $backoffStrategy */
                $backoffStrategy = new $backoffStrategyClass(Arr::wrap(Arr::get($config, 'delay.backoff.options')));
                $delayStrategy->setBackoffStrategy($backoffStrategy);
            }

            if ($delayStrategy instanceof PrioritizeAware) {
                $delayStrategy->setPrioritize(Arr::get($config, 'delay.prioritize'));
            }

            $factory->setDelayStrategy($delayStrategy);
        }

        /** @var AmqpContext $context */
        $context = $factory->createContext();

        $this->dispatcher->listen(WorkerStopping::class, function () use ($context) {
            $context->close();
        });

        $worker = Arr::get($config, 'worker', 'default');

        if ($worker === 'default') {
            return new RabbitMQQueue($context, $config);
        }

        if ($worker === 'horizon') {
            $this->dispatcher->listen(JobFailed::class, RabbitMQFailedEvent::class);

            return new HorizonRabbitMQQueue($context, $config);
        }

        if ($worker instanceof RabbitMQQueue) {
            return new $worker($context, $config);
        }

        throw new InvalidArgumentException('Invalid worker.');
    }
}
