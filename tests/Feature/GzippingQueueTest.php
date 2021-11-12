<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use VladimirYuldashev\LaravelQueueRabbitMQ\Tests\Mocks\TestJob;

/**
 * @group functional
 */
class GzippingQueueTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('queue.connections.rabbitmq.options.queue', ['content_encoding' => 'gzip']);
    }

    public function testCompression(): void
    {
        Queue::push(new TestJob());

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame($job->getRawBody(), gzinflate($job->getRabbitMQMessage()->getBody()));
    }
}
