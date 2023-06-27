<?php

use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
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
        $manager = Manager::instance(container());

        $manager->withQueue($queue);

        return $manager->enqueue($task);
    }
}


if (!function_exists('enqueue_delayed')) {

    /**
     * @param TaskInterface $task
     * @param string        $queue
     *
     * @return Manager
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     */
    function enqueue_delayed(TaskInterface $task, string $queue = Manager::QUEUE_NAME, int $delay = 0): Manager
    {
        $manager = Manager::instance(container());

        $manager->context()->setDelayStrategy(new RabbitMqDlxDelayStrategy());

        $manager->producer()->setDeliveryDelay($delay * 1000); // seconds * 1000 = milliseconds

        $manager->withQueue($queue);

        return $manager->enqueue($task);
    }
}
