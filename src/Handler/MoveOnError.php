<?php

namespace Spartan\Queue\Handler;

use Spartan\Queue\Definition\TaskInterface;
use Spartan\Queue\Manager;

class MoveOnError
{
    public function __invoke(Manager $manager, TaskInterface $task, \Exception $e, string $queueName)
    {
        $task->withAttributes([
            'exception' => [
                'error'    => $e->getMessage(),
                'file'     => $e->getFile() . ':' . $e->getLine(),
                'code'     => $e->getCode(),
                'trace'    => $e->getTrace(),
                'previous' => $e->getPrevious()
                    ? [
                        'error' => $e->getPrevious()->getMessage(),
                        'file'  => $e->getPrevious()->getFile() . ':' . $e->getPrevious()->getLine(),
                        'code'  => $e->getPrevious()->getCode(),
                        'trace' => $e->getPrevious()->getTrace(),
                    ]
                    : null,
            ],
        ]);

        enqueue($task, "{$queueName}_error");
    }
}
