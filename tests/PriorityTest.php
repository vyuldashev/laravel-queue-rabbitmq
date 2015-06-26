<?php

use FintechFab\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob as RabbitMQJob;

require_once 'TestCase.php';

class PriorityTest extends \TestCase {

    public function testPriorityTest() {
        $this->app->config['queue'] = $this->getBaseConfig([
            'queues_params' => [
                'test_priority' => [
                    'arguments' => [
                        'x-max-priority' => 255,
                    ],
                ],
            ],
        ]);

        $this->app->register('FintechFab\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider');

        /* priority test */

        $queue = 'test_priority';

        Queue::delete($queue);

        // push 1, then 2 (and 2 with higher priority)
        Queue::push($queue, ['data' => 1], $queue, ['priority' => 10]);
        Queue::push($queue, ['data' => 2], $queue, ['priority' => 20]);

        $tag = 'tag';

        $order = [];

        Queue::subscribe($queue, $tag, function (RabbitMQJob $job) use (& $order, $tag) {
            $data = json_decode($job->getRawBody(), 'assoc')['data']['data'];
            $order []= $data;
            $job->delete();

            if (sizeof($order) == 2) {
                Queue::unsubscribe($tag);
            }
        });

        // consume 2, then 1
        $this->assertEquals($order, [2, 1]);

        /* priority test with delay */

        $delay = 1;
        $step = 0;
        $time1 = microtime(true);

        Queue::delete($queue);
        Queue::delete($queue . '_deferred_' . $delay);

        Queue::push($queue, ['data' => 1], $queue, ['priority' => 1]);

        Queue::subscribe($queue, $tag, function (RabbitMQJob $job) use (& $step, $tag, $delay) {
            $data = json_decode($job->getRawBody(), 'assoc')['data']['data'];
            if ($step == 0) {
                // delay at first step
                $job->release($delay);
            } else {
                // delete at second step
                $job->delete();
            }

            if (++ $step == 2) {
                Queue::unsubscribe($tag);
            }
        });

        $time2 = microtime(true);
        //var_dump("$time2 - $time1 = " . ($time2 - $time1));
        $this->assertTrue($time2 - $time1 >= $delay);

        Queue::delete($queue);
        Queue::delete($queue . '_deferred_' . $delay);
    }

}
