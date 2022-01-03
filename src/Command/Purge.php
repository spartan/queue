<?php

namespace Spartan\Queue\Command;

use Spartan\Console\Command;
use Spartan\Queue\Manager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Purge Command
 *
 * @property string $con
 * @property string $queue
 *
 * @package Spartan\Event
 * @author  Iulian N. <iulian@spartanphp.com>
 * @license LICENSE MIT
 */
class Purge extends Command
{
    protected function configure(): void
    {
        $this->withSynopsis('queue:purge', 'Purge a queue')
             ->withOption('con', 'Connection to use. Can also be a DSN.')
             ->withOption('queue', 'Queue name');
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
             ->purgeQueue();

        return 0;
    }
}
