<?php

namespace Spartan\Event\Command;

use Spartan\Console\Command;
use Spartan\Enum\Priority;
use Spartan\Queue\Manager;
use Spartan\Queue\Task\Consumer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Stop Command
 *
 * @property string $con
 * @property string $queue
 * @property string $consumers
 *
 * @package Spartan\Event
 * @author  Iulian N. <iulian@spartanphp.com>
 * @license LICENSE MIT
 */
class Stop extends Command
{
    protected function configure(): void
    {
        $this->withSynopsis('queue:stop', 'Stop consuming')
             ->withOption('con', 'Connection to use. Can also be a DSN.')
             ->withOption('queue', 'Queue name')
             ->withOption('graceful', 'Allow current tasks to finish that are already queued');
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
        $this->loadEnv();

        $manager = Start::manager($this->con)
                        ->withQueue($this->queue ?? Manager::QUEUE_NAME)
                        ->withPriority(Manager::PRIORITY_VERY_HIGH);

        if ($this->isOptionPresent('graceful')) {
            // graceful
            $manager->enqueue(new Consumer(0));
        } elseif ($this->isOptionPresent('queue')) {
            passthru("ps aux | grep -i queue={$this->queue} | awk '{print $2}'| xargs kill -9");
        } else {
            passthru('ps aux | grep -i queue:start | awk \'{print $2}\'| xargs kill -9');
        }

        return 0;
    }
}
