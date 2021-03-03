<?php

use Spartan\Queue\Definition\TaskInterface;
use Spartan\Queue\Manager;

if (!function_exists('enqueue')) {

    /**
     * @param TaskInterface $task
     * @param string        $queue
     *
     * @return Manager
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     */
    function enqueue(TaskInterface $task, string $queue = Manager::QUEUE_NAME): Manager
    {
        static $manager;

        if (!$manager) {
            $manager = Manager::instance(container());
        }

        return $manager->withQueue($queue)
                       ->enqueue($task);
    }
}
