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

        $subscriptionConsumer->consume($timeout);

        return $this;
    }

    /**
     * @return $this
     */
    public function close(): self
    {
        $this->context->close();

        return $this;
    }

    /**
     * @param SubscriptionConsumer $subscriptionConsumer
     *
     * @return \Closure
     */
    public function closure(SubscriptionConsumer $subscriptionConsumer)
    {
        return function (Message $message, Consumer $consumer) use ($subscriptionConsumer) {
            /** @var TaskInterface $task */
            $task = \Opis\Closure\unserialize($message->getBody());

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
            } catch (\Exception $e) {
                // if errors are not handled by task
                if ($this->errHandler) {
                    $className = trim($this->errHandler, '\'"');
                    $object    = new $className;
                    $object($this, $task, $e, $consumer->getQueue()->getQueueName());
                } else {
                    throw new ConsumerException(
                        (string)json_encode(
                            [
                                'message' => $e->getMessage(),
                                'file'    => $e->getFile() . ':' . $e->getLine(),
                                'trace'   => $e->getTrace(),
                                'queue'   => $consumer->getQueue()->getQueueName(),
                                'task'    => $message->getBody(),
                                'time'    => date('Y-m-d H:i:s'),
                            ]
                        )
                    );
                }
            }

            $consumer->acknowledge($message);

            if ($this->ackHandler) {
                $className = trim($this->ackHandler, '\'"');
                $object    = new $className;
                $object($this, $message);
            }

            return true;
        };
    }
}
