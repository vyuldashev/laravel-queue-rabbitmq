<?php

use FintechFab\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob as RabbitMQJob;

require_once 'TestCase.php';

class PrefixTest extends \TestCase {

    public function testPrefixTest() {
        $this->app->config['queue'] = $this->getBaseConfig([
            'prefix' => 'prefix_',
        ]);

        $this->app->register('FintechFab\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider');

        $queue = 'queue_with_prefix';

        Queue::delete($queue);
        Queue::delete($queue . '_deferred_1');

        $count = 10; //rand(10, 20);
        foreach (range(1, $count - 1) as $num) {
            Queue::push($queue, ['data' => $num], $queue);
        }
        // delay one for second
        Queue::later('1', $queue, ['data' => $num], $queue);

        $this->assertTrue(Queue::getMessageCount($queue) === $count - 1);

        Queue::subscribe($queue, 'sub', function (RabbitMQJob $job) use (& $count) {
            $job->delete();
            $count --;
            if ($count == 0) {
                Queue::unsubscribe('sub');
            }
        });

        $this->assertTrue(0 === $count);

        Queue::delete($queue);
        Queue::delete($queue . '_deferred_1');
    }

}
