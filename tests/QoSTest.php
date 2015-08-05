<?php

use FintechFab\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob as RabbitMQJob;

require_once 'TestCase.php';

// dirty way to organize shared memory is to use global variables
$catch_me = null;
$second_consumer_subscribed = false;

class QosTest extends \TestCase {

    public function testQoSTest() {
        $prefetch_count = 3;

        $this->app->config['queue'] = $this->getBaseConfig([
            'queues_params' => [
                'test_qos' => [
                    'prefetch_count' => $prefetch_count,
                ],
            ],
        ]);

        $this->app->register('FintechFab\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider');

        Queue::delete('test_qos');

        Queue::push('test_qos', ['data' => 1], 'test_qos');
        Queue::push('test_qos', ['data' => 2], 'test_qos');
        Queue::push('test_qos', ['data' => 3], 'test_qos');
        Queue::push('test_qos', ['data' => 4], 'test_qos');
        Queue::push('test_qos', ['data' => 5], 'test_qos');

        $count = 0;
        $tag1 = 'tag1';
        $second = false;

        // this (1st) subscription prefetches $prefetch_count = 3 items...
        Queue::subscribe('test_qos', $tag1, function (RabbitMQJob $job) use ($tag1) {
            global $second_consumer_subscribed;

            $tag2 = 'tag2';

            if (! $second_consumer_subscribed) {
                $second_consumer_subscribed = true;
                // ... so this (2nd) subscription ...
                Queue::subscribe('test_qos', $tag2, function (RabbitMQJob $job) use ($tag1, $tag2) {

                    // ... fetches $prefetch_count + 1 item ...
                    global $catch_me;
                    $catch_me = json_decode($job->getRawBody(), 'assoc')['data']['data'];

                    // it is caught, get out of here
                    Queue::unsubscribe($tag2);
                    Queue::unsubscribe($tag1);

                    $job->delete();
                });
            }

            $job->delete();
        });

        // ... and let's compare
        global $catch_me;
        $this->assertEquals($catch_me, $prefetch_count + 1);

        Queue::delete('test_qos');

    }

}
