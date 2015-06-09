<?php

use FintechFab\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob as RabbitMQJob;

require_once 'TestCase.php';

class GetMessageCountTest extends \TestCase {

    public function testGetMessageCountTest() {
        $this->app->config['queue'] = $this->getBaseConfig([]);

        $this->app->register('FintechFab\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider');

        $queue = 'test_queue';

        Queue::delete($queue);

        $count = rand(10, 20);
        foreach (range(1, $count) as $num) {
            Queue::push($queue, ['data' => $num], $queue);
        }

        $this->assertTrue(Queue::getMessageCount($queue) === $count);

        Queue::delete($queue);
    }

}
