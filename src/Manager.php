<?php

namespace Spartan\Queue;

use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpProducer;
use Interop\Amqp\AmqpQueue;
use Interop\Queue\ConnectionFactory;
use Interop\Queue\Consumer;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Producer;
use Interop\Queue\Queue;
use Interop\Queue\SubscriptionConsumer;
use Interop\Queue\Topic;
use Psr\Container\ContainerInterface;
use Spartan\Queue\Definition\TaskInterface;
use Spartan\Queue\Exception\ConsumerException;

/**
 * Queue Manager
 *
 * @package Spartan\Queue
 * @author  Iulian N. <iulian@spartanphp.com>
 * @license LICENSE MIT
 */
class Manager
{
    const QUEUE_NAME = 'pipeline';

    const ADAPTER_FACTORY_MAP = [
        'rabbit'    => 'Enqueue\AmqpExt\AmqpConnectionFactory',
        'redis'     => 'Enqueue\Redis\RedisConnectionFactory',
        'beanstalk' => 'Enqueue\Pheanstalk\PheanstalkConnectionFactory',
        'kafka'     => 'Enqueue\RdKafka\RdKafkaConnectionFactory',
    ];

    const FLAG_DURABLE  = 1;
    const FLAG_PRIORITY = 2;

    const PRIORITY_VERY_HIGH = 10;
    const PRIORITY_HIGH      = 9;
    const PRIORITY_NORMAL    = 5;
    const PRIORITY_LOW       = 1;
    const PRIORITY_VERY_LOW  = 0;

    /**
     * Max time (ms) a blocking wait lasts before the stop flag is checked again
     */
    const CONSUME_SLICE_MS = 1000;

    protected Context $context;

    /**
     * @var Queue|AmqpQueue|null
     */
    protected ?Queue $queue = null;

    protected ?Topic $topic = null;

    protected ?Producer $producer = null;

    protected ?ContainerInterface $container = null;

    protected bool $verbosity = false;

    /**
     * Set by a stop signal (or stop()): no new task is started, the consume loop exits
     */
    protected bool $stopping = false;

    protected bool $signalsBlocked = false;

    /**
     * @var mixed
     */
    protected $ackHandler = null;

    /**
     * @var mixed
     */
    protected $errHandler = null;

    /**
     * Manager constructor.
     *
     * @param Context                 $context
     * @param ContainerInterface|null $container
     */
    public function __construct(Context $context, ContainerInterface $container = null)
    {
        $this->context   = $context;
        $this->container = $container;
    }

    /**
     * @param ContainerInterface|null $container
     *
     * @return Manager
     */
    public static function instance(ContainerInterface $container = null)
    {
        $config  = require './config/queue.php';
        $context = Manager::createContext($config);

        return new Manager($context, $container ?: container());
    }

    /**
     * @param mixed[] $config
     *
     * @return Context
     */
    public static function createContext(array $config): Context
    {
        $adapter = $config['adapter'];
        if (!isset($config[$adapter])) {
            throw new \InvalidArgumentException("Queue adapter is not supported: {$adapter}");
        }


        $adapterConfig = $config[$adapter];
        $adapterClass  = self::ADAPTER_FACTORY_MAP[$adapter];

        /** @var ConnectionFactory $factory */
        $factory = new $adapterClass($adapterConfig);

        return $factory->createContext();
    }

    /**
     * @param string $name
     * @param int    $flags
     *
     * @return $this
     */
    public function withQueue(string $name, int $flags = 0, array $args = [])
    {
        $this->queue = $this->context->createQueue($name);

        if ($flags && method_exists($this->queue, 'setFlags')) {
            $this->queue->setFlags($flags);
        }

        if (getenv('QUEUE_RABBIT_PERSISTENCE')) {
            $this->queue->addFlag(AmqpQueue::FLAG_DURABLE);
        }

        if (getenv('QUEUE_RABBIT_PRIORITY')) {
            $this->queue->setArguments(
                ['x-max-priority' => (int)getenv('QUEUE_RABBIT_PRIORITY')] + $args
            );
        }

        $this->context->declareQueue($this->queue);

        return $this;
    }

    /**
     * @param string $name
     *
     * @return $this
     * @throws \Interop\Queue\Exception\TemporaryQueueNotSupportedException
     */
    public function withTemporaryQueue(string $name): self
    {
        $this->queue = $this->context->createTemporaryQueue();

        return $this;
    }

    /**
     * @return $this
     * @throws \Interop\Queue\Exception\PurgeQueueNotSupportedException
     */
    public function purgeQueue()
    {
        $this->context->purgeQueue($this->queue());

        return $this;
    }

    /**
     * @param string $name
     * @param int    $flags
     *
     * @return $this
     */
    public function withTopic(string $name, int $flags = 0)
    {
        $this->topic = $this->context->createTopic($name);

        if ($flags && method_exists($this->topic, 'setFlags')) {
            $this->topic->setFlags($flags);
        }

        return $this;
    }

    /**
     * @param bool $verbosity
     *
     * @return $this
     */
    public function withVerbosity($verbosity = true)
    {
        $this->verbosity = $verbosity;

        return $this;
    }

    public function withErrHandler($handler)
    {
        $this->errHandler = $handler;

        return $this;
    }

    public function withAckHandler($handler)
    {
        $this->ackHandler = $handler;

        return $this;
    }

    /**
     * @return Context
     */
    public function context(): Context
    {
        return $this->context;
    }

    /**
     * @return AmqpQueue|Queue
     */
    public function queue(): Queue
    {
        if (!$this->queue) {
            $this->queue = $this->context->createQueue(self::QUEUE_NAME);
        }

        return $this->queue;
    }

    /**
     * @return Producer|AmqpProducer
     */
    public function producer(): Producer
    {
        if (!$this->producer) {
            $this->producer = $this->context->createProducer();
        }

        return $this->producer;
    }

    /**
     * @param int $priority
     *
     * @return Manager
     * @throws \Interop\Queue\Exception\PriorityNotSupportedException
     */
    public function withPriority(int $priority)
    {
        $this->producer()->setPriority($priority);

        return $this;
    }

    /**
     * @return Consumer|AmqpConsumer
     */
    public function consumer(): Consumer
    {
        $consumer = $this->context->createConsumer($this->queue());
        
        // Starting w/ PHP 8.1:
        // AMQPQueue::consume(): Passing null to parameter #3 ($consumer_tag) of type string is deprecated
        // in \Enqueue\AmqpExt\AmqpSubscriptionConsumer line 99
        $consumer->setConsumerTag('default');
        
        return $consumer;
    }

    /**
     * @param TaskInterface $task
     * @param mixed[]       $attributes
     *
     * @return Manager
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     */
    public function enqueue(TaskInterface $task, array $attributes = [], array $headers = []): self
    {
        $queueMessage = $this->context->createMessage(\Opis\Closure\serialize($task), [], $headers);

        if (getenv('QUEUE_RABBIT_PERSISTENCE')) {
            $queueMessage->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);
        }

        foreach ($attributes as $name => $value) {
            $queueMessage->{"set{$name}"}($value);
        }

        $this->producer()->send($this->queue(), $queueMessage);

        return $this;
    }

    /**
     * @param int $count
     * @param int $timeout
     *
     * @return Manager
     * @throws \Interop\Queue\Exception\SubscriptionConsumerNotSupportedException
     */
    public function dequeue(int $count = 1, int $timeout = 0)
    {
        $subscriptionConsumer = $this->context->createSubscriptionConsumer();

        for ($i = 0; $i < $count; $i++) {
            // we need a different consumer each time!
            $subscriptionConsumer->subscribe(
                $this->consumer(),
                $this->closure($subscriptionConsumer)
            );
        }

        if ($timeout > 0) {
            $subscriptionConsumer->consume($timeout);

            return $this;
        }

        $this->listenForStopSignals();

        // wait in short slices instead of blocking forever => an idle worker notices SIGTERM within a slice
        while (!$this->isStopping()) {
            $subscriptionConsumer->consume(self::CONSUME_SLICE_MS);
        }

        try {
            // basic.cancel => the broker stops delivering; unacked prefetched messages are requeued on close
            $subscriptionConsumer->unsubscribeAll();
            $this->context->close();
        } catch (\Throwable $e) {
            // the process is exiting anyway: a broken connection is not worth a failure
        }

        return $this;
    }

    /**
     * SIGTERM, SIGQUIT (k8s / docker stop, see stopSignals) or SIGINT => finish the current task, ack it, then exit.
     *
     * The signals are blocked and polled (isStopping) instead of handled: a pcntl handler is lost when the
     * signal arrives while ext-amqp waits inside consume() (the usual state of an idle worker), and the
     * worker usually runs as PID 1 in a container, where an unhandled SIGTERM is ignored anyway.
     * Blocked signals are inherited by child processes started from a task (exec, proc_open...).
     *
     * @return $this
     */
    public function listenForStopSignals(): self
    {
        if (function_exists('pcntl_sigprocmask')) {
            $this->signalsBlocked = pcntl_sigprocmask(SIG_BLOCK, $this->stopSignals());
        }

        return $this;
    }

    /**
     * SIGQUIT: images built FROM php:*-fpm inherit `STOPSIGNAL SIGQUIT` => docker stop / k8s send SIGQUIT, not SIGTERM.
     * A method, not a constant: the SIG* constants exist only with ext-pcntl.
     *
     * @return int[]
     */
    protected function stopSignals(): array
    {
        return [SIGTERM, SIGINT, SIGQUIT];
    }

    /**
     * @return $this
     */
    public function stop(): self
    {
        $this->stopping = true;

        return $this;
    }

    /**
     * Picks up a pending stop signal (non-blocking)
     */
    public function isStopping(): bool
    {
        if (!$this->stopping && $this->signalsBlocked) {
            $signal = pcntl_sigtimedwait($this->stopSignals(), $info, 0, 0);
            if ($signal > 0) {
                if ($this->verbosity) {
                    echo "Signal {$signal} received, stopping...\n";
                }
                $this->stopping = true;
            }
        }

        return $this->stopping;
    }

    /**
     * @return $this
     */
    public function close(): self
    {
        $this->context->close();

        return $this;
    }

    protected function errorJson(\Throwable $e, Consumer $consumer, string $task): string
    {
        return (string)json_encode(
            [
                'message' => $e->getMessage(),
                'error'   => get_class($e),
                'file'    => $e->getFile() . ':' . $e->getLine(),
                'trace'   => $e->getTraceAsString(), // getTrace() args may not be encodable
                'queue'   => $consumer->getQueue()->getQueueName(),
                'task'    => substr($task, 0, 10000), // serialized body can be large
                'time'    => date('Y-m-d H:i:s'),
            ],
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
    }

    protected function logError(\Throwable $e, Consumer $consumer, string $task): void
    {
        error_log('[spartan/queue] ' . $this->errorJson($e, $consumer, $task));
    }

    /**
     * @param SubscriptionConsumer $subscriptionConsumer
     *
     * @return \Closure
     */
    public function closure(SubscriptionConsumer $subscriptionConsumer)
    {
        return function (Message $message, Consumer $consumer) use ($subscriptionConsumer) {
            // delivered after the stop signal => back to the queue, for the next worker
            if ($this->isStopping()) {
                $consumer->reject($message, true);

                return false; // stops consume()
            }

            /*
             * A message is always acknowledged, even when its task fails: an unacked message is
             * redelivered to the restarted worker => fails again => the queue is stuck (poison message).
             * \Throwable, not \Exception: an \Error (undefined method, TypeError...) must not kill the worker.
             */
            try {
                /** @var TaskInterface $task */
                $task = \Opis\Closure\unserialize($message->getBody());
                if (!$task instanceof TaskInterface) {
                    throw new \UnexpectedValueException('Message body is not a task');
                }
            } catch (\Throwable $e) {
                // no task => nothing to hand to the error handler
                $consumer->acknowledge($message);
                $this->logError($e, $consumer, $message->getBody());

                return true;
            }

            if ($this->verbosity) {
                echo 'Received ' . get_class($task) . "...\n";
            }

            if ($task instanceof Task\Consumer) {
                $consumer->acknowledge($message);

                // unsub all
                $subscriptionConsumer->unsubscribeAll();

                // sub new count
                for ($i = 0; $i < $task->count(); $i++) {
                    $subscriptionConsumer->subscribe(
                        $this->consumer(),
                        $this->closure($subscriptionConsumer)
                    );
                }

                return true;
            }

            try {
                $task();
                if (!$task->isFailed()) {
                    $task->markAsFinished();
                }
            } catch (\Throwable $e) {
                // if errors are not handled by task
                if (!$this->errHandler) {
                    // ack first => the worker stops (as before) but the message is not redelivered
                    $consumer->acknowledge($message);

                    throw new ConsumerException($this->errorJson($e, $consumer, $message->getBody()));
                }

                try {
                    $className = trim($this->errHandler, '\'"');
                    $object    = new $className;
                    $object($this, $task, $e, $consumer->getQueue()->getQueueName());
                } catch (\Throwable $handlerError) {
                    // a failing error handler must not stop the ack either
                    $this->logError($e, $consumer, $message->getBody());
                    $this->logError($handlerError, $consumer, get_class($task));
                }
            }

            $consumer->acknowledge($message);

            if ($this->ackHandler) {
                $className = trim($this->ackHandler, '\'"');
                $object    = new $className;
                $object($this, $message);
            }

            // stop signal received while the task ran => stop consume() now that it is acked
            return !$this->isStopping();
        };
    }
}
