<?php

/*
 * Queue configurations for different adapters
 */

return [
    'adapter' => getenv('QUEUE_ADAPTER') ?: 'rabbit',

    /*
     * Rabbit adapter configuration
     *
     * @see https://php-enqueue.github.io/transport/amqp/
     */
    'rabbit'  => [
        'host'      => getenv('QUEUE_RABBIT_HOST'),
        'port'      => getenv('QUEUE_RABBIT_PORT'),
        'vhost'     => getenv('QUEUE_RABBIT_VHOST') ?: '/',
        'user'      => getenv('QUEUE_RABBIT_USER'),
        'pass'      => getenv('QUEUE_RABBIT_PASS'),
        'persisted' => false,
    ],

    /*
     * Redis adapter configuration
     *
     * @see https://php-enqueue.github.io/transport/redis/
     */
    'redis' => [
        'host'              => getenv('QUEUE_REDIS_HOST'),
        'port'              => getenv('QUEUE_REDIS_PORT'),
        'scheme_extensions' => ['phpredis'],
    ],

    /*
     * Beanstalk adapter configuration
     *
     * @see https://php-enqueue.github.io/transport/pheanstalk/
     */
    'beanstalk' => [
        'host' => getenv('QUEUE_BEANSTALK_HOST'),
        'port' => getenv('QUEUE_BEANSTALK_PORT'),
    ],

    /*
     * Kafka adapter configuration
     *
     * @see https://php-enqueue.github.io/transport/kafka/
     */
    'kafka' => [
        'global' => [
            'group.id'             => uniqid('', true),
            'metadata.broker.list' => getenv('QUEUE_KAFKA_HOST') . ':' . getenv('QUEUE_KAFKA_PORT'),
            'enable.auto.commit'   => 'false',
        ],
        'topic'  => [
            'auto.offset.reset' => 'beginning',
        ],
    ]
];
