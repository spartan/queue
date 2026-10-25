<?php

namespace Spartan\Queue\Test;

use PHPUnit\Framework\TestCase;
use Spartan\Queue\Definition\TaskInterface;
use Spartan\Queue\Definition\TaskTrait;
use Spartan\Queue\Handler\MoveOnError;

/**
 * The failed task is re-queued with its exception attached => the attributes must stay serializable
 */
class MoveOnErrorTest extends TestCase
{
    public function testExceptionWithObjectsInTraceKeepsTaskSerializable(): void
    {
        // workers run with arguments in traces (php.ini-production turns them off)
        $ignoreArgs = ini_set('zend.exception_ignore_args', '0');

        try {
            $e = $this->failWith(new TypedClosureHolder(), new \RuntimeException('previous'));
        } finally {
            ini_set('zend.exception_ignore_args', (string)$ignoreArgs);
        }

        // the raw trace cannot be serialized
        try {
            \Opis\Closure\serialize((new MovedTask())->withAttributes(['trace' => $e->getTrace()]));
            $this->fail('getTrace() with objects should not be serializable');
        } catch (\TypeError $typeError) {
            $this->assertStringContainsString('TypedClosureHolder', $typeError->getMessage());
        }

        $task = (new MovedTask())->withAttributes(['exception' => MoveOnError::exception($e)]);

        $restored = \Opis\Closure\unserialize(\Opis\Closure\serialize($task));
        $exception = $restored->attributes()['exception'];

        $this->assertSame('failed', $exception['error']);
        $this->assertIsString($exception['trace']);
        $this->assertStringContainsString('failWith', $exception['trace']);
        $this->assertSame('previous', $exception['previous']['error']);
        $this->assertIsString($exception['previous']['trace']);
    }

    /**
     * Arguments end up in getTrace()
     */
    protected function failWith(TypedClosureHolder $holder, \Throwable $previous): \Throwable
    {
        return new \LogicException('failed', 0, $previous);
    }
}

/**
 * Like Symfony's InputArgument::$suggestedValues => Opis cannot wrap the closure into a typed property
 */
class TypedClosureHolder
{
    public \Closure $closure;

    public function __construct()
    {
        $this->closure = fn() => true;
    }
}

class MovedTask implements TaskInterface
{
    use TaskTrait;

    public function __invoke(): void
    {
    }
}
