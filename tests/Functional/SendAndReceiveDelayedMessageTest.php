<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Functional;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Interop\Amqp\AmqpTopic;
use PHPUnit\Framework\TestCase;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class SendAndReceiveDelayedMessageTest extends TestCase
{
    public function test()
    {
        $config = [
            'factory_class' => AmqpConnectionFactory::class,
            'dsn'      => null,
            'host'     => getenv('HOST'),
            'port'     => getenv('PORT'),
            'login'    => 'guest',
            'password' => 'guest',
            'vhost'    => '/',
            'queue'              => 'queue_name',
            'exchange_declare'   => true,
            'queue_declare'   => true,
            'queue_declare_bind' => true,
            'queue_params' => [
                'passive'     => false,
                'durable'     => true,
                'exclusive'   => false,
                'auto_delete' => false,
                'arguments'   => null,
            ],
            'exchange_params' => [
                'name'        => null,
                'type'        => AmqpTopic::TYPE_DIRECT,
                'passive'     => false,
                'durable'     => true,
                'auto_delete' => false,
            ],
            'ssl_params' => [
                'ssl_on'        => false,
                'cafile'        => null,
                'local_cert'    => null,
                'verify_peer'   => true,
                'passphrase'    => null,
            ]
        ];

        $connector = new RabbitMQConnector(new Dispatcher());
        /** @var RabbitMQQueue $queue */
        $queue = $connector->connect($config);
        $queue->setContainer(new Container());

        // we need it to declare exchange\queue on RabbitMQ side.
        $queue->pushRaw('something');

        $queue->getContext()->purgeQueue($queue->getContext()->createQueue('queue_name'));

        $expectedPayload = __METHOD__.microtime(true);

        $queue->pushRaw($expectedPayload, null, ['delay' => 3]);

        sleep(1);

        $this->assertNull($queue->pop());

        sleep(4);

        $job = $queue->pop();

        $this->assertInstanceOf(RabbitMQJob::class, $job);
        $this->assertSame($expectedPayload, $job->getRawBody());

        $job->delete();
    }
}
