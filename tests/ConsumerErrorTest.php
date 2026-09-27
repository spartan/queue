<?php

namespace Spartan\Queue\Test;

use Interop\Queue\Consumer;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Queue;
use Interop\Queue\SubscriptionConsumer;
use PHPUnit\Framework\TestCase;
use Spartan\Queue\Definition\TaskInterface;
use Spartan\Queue\Definition\TaskTrait;
use Spartan\Queue\Exception\ConsumerException;
use Spartan\Queue\Manager;

/**
 * A failing task must never leave its message unacked: RabbitMQ would redeliver it to the restarted
 * worker => fails again => the whole queue is stuck (poison message).
 */
class ConsumerErrorTest extends TestCase
{
    public static array $handled = [];

    protected function setUp(): void
    {
        self::$handled = [];
        ini_set('error_log', '/dev/null'); // logError() output
    }

    protected function consume(string $body, ?string $errHandler, int $expectedAcks = 1)
    {
        $queue = $this->createMock(Queue::class);
        $queue->method('getQueueName')->willReturn('test');

        $message = $this->createMock(Message::class);
        $message->method('getBody')->willReturn($body);

        $consumer = $this->createMock(Consumer::class);
        $consumer->method('getQueue')->willReturn($queue);
        $consumer->expects($this->exactly($expectedAcks))->method('acknowledge')->with($message);

        $manager = (new Manager($this->createMock(Context::class)))->withErrHandler($errHandler);

        return $manager->closure($this->createMock(SubscriptionConsumer::class))($message, $consumer);
    }

    protected static function body(TaskInterface $task): string
    {
        return \Opis\Closure\serialize($task);
    }

    public function testErrorIsHandledAndAcked(): void
    {
        // regression: catch (\Exception) let an \Error kill the worker before acknowledge()
        $this->assertTrue($this->consume(self::body(new FailingTask('error')), RecordingHandler::class));
        $this->assertSame([\Error::class], self::$handled);
    }

    public function testExceptionIsHandledAndAcked(): void
    {
        $this->assertTrue($this->consume(self::body(new FailingTask('exception')), RecordingHandler::class));
        $this->assertSame([\RuntimeException::class], self::$handled);
    }

    public function testFailingHandlerStillAcks(): void
    {
        $this->assertTrue($this->consume(self::body(new FailingTask('error')), ThrowingHandler::class));
    }

    public function testExceptionOnlyHandlerStillAcks(): void
    {
        // a handler typed (\Exception $e) gets an \Error => TypeError inside the handler => still acked
        $this->assertTrue($this->consume(self::body(new FailingTask('error')), ExceptionOnlyHandler::class));
    }

    public function testNoHandlerAcksThenThrows(): void
    {
        $this->expectException(ConsumerException::class);
        $this->consume(self::body(new FailingTask('error')), null);
    }

    public function testUnreadableBodyIsAcked(): void
    {
        $this->assertTrue($this->consume('not a serialized task', RecordingHandler::class));
        $this->assertSame([], self::$handled);
    }

    public function testSuccessfulTaskIsAckedOnce(): void
    {
        $this->assertTrue($this->consume(self::body(new FailingTask('none')), RecordingHandler::class));
        $this->assertSame([], self::$handled);
    }
}

class FailingTask implements TaskInterface
{
    use TaskTrait;

    public function __construct(protected string $mode)
    {
    }

    public function __invoke(): void
    {
        if ($this->mode == 'error') {
            $this->undefinedMethod(); // \Error
        }
        if ($this->mode == 'exception') {
            throw new \RuntimeException('failed');
        }
    }
}

class RecordingHandler
{
    public function __invoke(Manager $manager, TaskInterface $task, \Throwable $e, string $queueName)
    {
        ConsumerErrorTest::$handled[] = get_class($e);
    }
}

class ThrowingHandler
{
    public function __invoke(Manager $manager, TaskInterface $task, \Throwable $e, string $queueName)
    {
        throw new \RuntimeException('handler failed');
    }
}

class ExceptionOnlyHandler
{
    public function __invoke(Manager $manager, TaskInterface $task, \Exception $e, string $queueName)
    {
    }
}
