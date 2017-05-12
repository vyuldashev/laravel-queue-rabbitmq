<?php

use Illuminate\Container\Container;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

/**
 * @property \Mockery\MockInterface connection
 * @property \Mockery\MockInterface channel
 * @property array config
 * @property RabbitMQQueue queue
 */
class RabbitMQQueueTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->connection = Mockery::mock(AMQPStreamConnection::class);
        $this->channel = Mockery::mock(AMQPChannel::class);

        $this->connection->shouldReceive('channel')->andReturn($this->channel);

        $this->config = [
            'queue'        => str_random(),
            'queue_params' => [
                'passive'     => false,
                'durable'     => true,
                'exclusive'   => false,
                'auto_delete' => false,
            ],
            'exchange_params' => [
                'name'        => 'exchange_name',
                'type'        => 'direct',
                'passive'     => false,
                'durable'     => true,
                'auto_delete' => false,
            ],
            'exchange_declare'   => true,
            'queue_declare_bind' => true,
        ];

        $this->queue = new RabbitMQQueue($this->connection, $this->config);
    }

    public function test_size()
    {
        $this->queue->size();
    }

    public function test_push()
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
            $this->config['queue_params']['auto_delete']
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

    public function test_later()
    {
        $job = new TestJob();
        $data = [];
        $delay = mt_rand(10, 60);

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
            $this->config['queue'].'_deferred_'.$delay,
            $this->config['exchange_params']['name'],
            $this->config['queue'].'_deferred_'.$delay
        )->once();

        $this->channel->shouldReceive('basic_publish')->once();

        $correlationId = $this->queue->later($delay, $job, $data);

        $this->assertEquals(23, strlen($correlationId));
    }

    public function test_pop()
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
            $this->config['queue_params']['auto_delete']
        )->once();

        $this->channel->shouldReceive('queue_bind')->with(
            $this->config['queue'],
            $this->config['exchange_params']['name'],
            $this->config['queue']
        )->once();

        $this->channel->shouldReceive('basic_get')->with($this->config['queue'])->andReturn($message)->once();

        $this->queue->setContainer(Mockery::mock(Container::class));
        $this->queue->pop();
    }

    public function test_setAttempts()
    {
        $count = mt_rand();

        $this->queue->setAttempts($count);
    }

    public function test_setCorrelationId()
    {
        $id = str_random();

        $this->queue->setCorrelationId($id);
    }
}
