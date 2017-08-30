<?php

use Illuminate\Container\Container;
use Mockery\Mock;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQQueueTest extends TestCase
{
    /** @var Mock|mixed */
    private $connection;

    /** @var Mock */
    private $channel;

    /** @var array */
    private $config;

    /** @var RabbitMQQueue */
    private $queue;

    public function setUp()
    {
        parent::setUp();

        $this->connection = Mockery::mock(AMQPStreamConnection::class);
        $this->channel = Mockery::mock(AMQPChannel::class);

        $this->connection->shouldReceive('channel')->andReturn($this->channel);

        $this->config = [
            'queue' => str_random(),
            'queue_params' => [
                'passive' => false,
                'durable' => true,
                'exclusive' => false,
                'auto_delete' => false,
                'arguments' => null,
            ],
            'exchange_params' => [
                'name' => 'exchange_name',
                'type' => 'direct',
                'passive' => false,
                'durable' => true,
                'auto_delete' => false,
            ],
            'exchange_declare' => true,
            'queue_declare_bind' => true,
        ];

        $this->queue = new RabbitMQQueue($this->connection, $this->config);
    }

    public function testSize()
    {
        $messageCount = 5;
        $consumerCount = 1;

        $this->channel->shouldReceive('queue_declare')->with(
            $this->config['queue'],
            true
        )->once()
            ->andReturn([$this->config['queue'], $messageCount, $consumerCount]);

        $size = $this->queue->size();

        $this->assertEquals($messageCount, $size);
    }

    public function testPush()
    {
        $job = new TestJob();
        $data = [];

        $this->channel->shouldReceive('exchange_declare')->with(
            $this->config['exchange_params']['name'],
            $this->config['exchange_params']['type'],
            $this->config['exchange_params']['passive'],
            $this->config['exchange_params']['durable'],
            $this->config['exchange_params']['auto_delete']
        )->once();

        $this->channel->shouldReceive('queue_declare')->with(
            $this->config['queue'],
            $this->config['queue_params']['passive'],
            $this->config['queue_params']['durable'],
            $this->config['queue_params']['exclusive'],
            $this->config['queue_params']['auto_delete'],
            false,
            Mockery::any()
        )->once();

        $this->channel->shouldReceive('queue_bind')->with(
            $this->config['queue'],
            $this->config['exchange_params']['name'],
            $this->config['queue']
        )->once();

        $this->channel->shouldReceive('basic_publish')->once();

        $correlationId = $this->queue->push($job, $data);

        $this->assertEquals(23, strlen($correlationId));
    }

    public function testLater()
    {
        $job = new TestJob();
        $data = [];
        $delay = random_int(10, 60);

        $this->channel->shouldReceive('exchange_declare')->with(
            $this->config['exchange_params']['name'],
            $this->config['exchange_params']['type'],
            $this->config['exchange_params']['passive'],
            $this->config['exchange_params']['durable'],
            $this->config['exchange_params']['auto_delete']
        )->once();

        $this->channel->shouldReceive('queue_declare')->once();

        // main queue
        $this->channel->shouldReceive('queue_bind')->with(
            $this->config['queue'],
            $this->config['exchange_params']['name'],
            $this->config['queue']
        )->once();

        // delayed queue
        $this->channel->shouldReceive('queue_bind')->with(
            $this->config['queue'] . '_deferred_' . $delay,
            $this->config['exchange_params']['name'],
            $this->config['queue'] . '_deferred_' . $delay
        )->once();

        $this->channel->shouldReceive('basic_publish')->once();

        $correlationId = $this->queue->later($delay, $job, $data);

        $this->assertEquals(23, strlen($correlationId));
    }

    public function testPop()
    {
        $message = Mockery::mock(AMQPMessage::class);

        $this->channel->shouldReceive('exchange_declare')->with(
            $this->config['exchange_params']['name'],
            $this->config['exchange_params']['type'],
            $this->config['exchange_params']['passive'],
            $this->config['exchange_params']['durable'],
            $this->config['exchange_params']['auto_delete']
        )->once();

        $this->channel->shouldReceive('queue_declare')->with(
            $this->config['queue'],
            $this->config['queue_params']['passive'],
            $this->config['queue_params']['durable'],
            $this->config['queue_params']['exclusive'],
            $this->config['queue_params']['auto_delete'],
            false,
            Mockery::any()
        )->once();

        $this->channel->shouldReceive('queue_bind')->with(
            $this->config['queue'],
            $this->config['exchange_params']['name'],
            $this->config['queue']
        )->once();

        $this->channel->shouldReceive('basic_get')->with($this->config['queue'])->andReturn($message)->once();

        /** @var Mock|mixed $container */
        $container = Mockery::mock(Container::class);

        $this->queue->setContainer($container);
        $this->queue->pop();
    }

    public function testSetAttempts()
    {
        $count = mt_rand();

        $this->queue->setAttempts($count);
    }

    public function testSetCorrelationId()
    {
        $id = str_random();

        $this->queue->setCorrelationId($id);
    }
}
