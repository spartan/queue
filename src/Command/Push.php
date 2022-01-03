<?php

namespace Spartan\Queue\Command;

use Spartan\Console\Command;
use Spartan\Queue\Manager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Push Command
 *
 * @property string $con
 * @property string $queue
 * @property string $task
 * @property int    $count
 * @property int    $priority
 *
 * @package Spartan\Event
 * @author  Iulian N. <iulian@spartanphp.com>
 * @license LICENSE MIT
 */
class Push extends Command
{
    protected function configure(): void
    {
        $this->withSynopsis('queue:push', 'Push a task to the queue (for testing)')
             ->withArgument('task', 'Task class')
             ->withOption('con', 'Connection to use. Can also be a DSN.')
             ->withOption('queue', 'Queue name')
             ->withOption('count', 'How many to push. For testing.', 1)
             ->withOption('priority', 'Priority');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $manager = Start::manager($this->con);
        $manager->withQueue($this->queue ?? Manager::QUEUE_NAME);

        if ($this->isOptionPresent('priority')) {
            $manager->withPriority($this->priority);
        }

        for ($i = 0; $i < $this->count; $i++) {
            $manager->enqueue(new $this->task);
        }

        return 0;
    }
}
