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
class Restart extends Command
{
    protected function configure(): void
    {
        $this->withSynopsis('queue:restart', 'Restart consuming')
             ->withOption('con', 'Connection to use. Can also be a DSN.')
             ->withOption('queue', 'Queue name')
             ->withOption('consumers', 'How many consumers to use (default=2)', 2);
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
        self::loadEnv();

        Start::manager($this->con)
             ->withQueue($this->queue ?? Manager::QUEUE_NAME)
             ->withPriority(Manager::PRIORITY_VERY_HIGH)
             ->enqueue(new Consumer((int)$this->consumers));

        return 0;
    }
}
