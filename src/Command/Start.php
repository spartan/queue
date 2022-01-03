<?php

namespace Spartan\Queue\Command;

use Spartan\Console\Command;
use Spartan\Queue\Manager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Start Command
 *
 * @property string $con
 * @property string $queue
 * @property int    $consumers
 * @property int    $wait
 * @property string $ack
 * @property string $err
 *
 * @package Spartan\Event
 * @author  Iulian N. <iulian@spartanphp.com>
 * @license LICENSE MIT
 */
class Start extends Command
{
    protected function configure(): void
    {
        $this->withSynopsis('queue:start', 'Start consuming')
             ->withOption('con', 'Connection to use. Can also be a DSN.')
             ->withOption('queue', 'Queue name', 'pipeline')
             ->withOption('consumers', 'How many consumers to use (default=2)', 2)
             ->withOption('wait', 'How many seconds to wait until making connection (default=0)', 0)
             ->withOption('ack', 'Ack handler - run each time after the message was acknowledged', null)
             ->withOption('err', 'Err handler - run each time when a task has failed', null);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws \Interop\Queue\Exception\SubscriptionConsumerNotSupportedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        self::loadEnv();

        if ($this->wait) {
            sleep($this->wait);
        }

        $manager = ($this->manager($this->con))
            ->withQueue($this->queue ?: Manager::QUEUE_NAME)
            ->withVerbosity($output->isVerbose())
            ->withErrHandler($this->err ?: null)
            ->withAckHandler($this->ack ?: null);

        if ($output->isVerbose()) {
            $output->writeln('Listening...');
        }

        $manager->dequeue($this->consumers, 0);

        return 0;
    }

    /**
     * @param mixed $con
     *
     * @return Manager
     */
    public static function manager($con): Manager
    {
        $config = require_once './config/queue.php';

        if ($con) {
            if (isset($config[$con])) {
                $adapterConfig = $config[$con];
                $context       = Manager::createContext(['adapter' => $con] + $adapterConfig);
            } else {
                $context = Manager::createContext($con);
            }
        } else {
            $context = Manager::createContext($config);
        }

        $container = function_exists('container') ? container() : null;

        return new Manager($context, $container);
    }
}
