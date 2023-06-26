<?php

use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Spartan\Queue\Definition\TaskInterface;
use Spartan\Queue\Manager;

if (!function_exists('enqueue')) {

    /**
     * @param TaskInterface $task
     * @param string        $queue
     * @param int           $delay  Delay in seconds (RabbitMQ only)
     *
     * @return Manager
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     */
    function enqueue(TaskInterface $task, string $queue = Manager::QUEUE_NAME, int $delay = 0): Manager
    {
        static $manager;

        if (!$manager) {
            $manager = Manager::instance(container());
        }

        if ($delay > 0) {
            $manager->context()->setDelayStrategy(new RabbitMqDlxDelayStrategy());
            $manager->producer()->setDeliveryDelay($delay * 1000); // seconds * 1000 = milliseconds
        }

        return $manager->withQueue($queue)
                       ->enqueue($task);
    }
}
