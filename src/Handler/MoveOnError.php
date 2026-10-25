<?php

namespace Spartan\Queue\Handler;

use Spartan\Queue\Definition\TaskInterface;
use Spartan\Queue\Manager;

class MoveOnError
{
    public function __invoke(Manager $manager, TaskInterface $task, \Throwable $e, string $queueName)
    {
        $task->withAttributes(['exception' => self::exception($e)]);

        enqueue($task, "{$queueName}_error");
    }

    /**
     * Traces as strings: getTrace() holds the call arguments (console app, commands, closures...)
     * and the task could not be serialized anymore
     *
     * @param \Throwable $e
     *
     * @return mixed[]
     */
    public static function exception(\Throwable $e): array
    {
        $previous = $e->getPrevious();

        return [
            'error'    => $e->getMessage(),
            'file'     => $e->getFile() . ':' . $e->getLine(),
            'code'     => $e->getCode(),
            'trace'    => $e->getTraceAsString(),
            'previous' => $previous
                ? [
                    'error' => $previous->getMessage(),
                    'file'  => $previous->getFile() . ':' . $previous->getLine(),
                    'code'  => $previous->getCode(),
                    'trace' => $previous->getTraceAsString(),
                ]
                : null,
        ];
    }
}
