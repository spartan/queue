<?php

namespace Spartan\Queue;

use Psr\Container\ContainerInterface;
use Spartan\Service\Container;
use Spartan\Service\Definition\ProviderInterface;
use Spartan\Service\Pipeline;

/**
 * ServiceProvider Queue
 *
 * @package Spartan\Queue
 * @author  Iulian N. <iulian@spartanphp.com>
 * @license LICENSE MIT
 */
class ServiceProvider implements ProviderInterface
{
    /**
     * @return mixed[]
     */
    public function singletons(): array
    {
        return [
            'queue'        => Manager::class,
            Manager::class => function ($c) {
                return Manager::instance($c);
            },
        ];
    }

    /**
     * @param ContainerInterface $container
     * @param Pipeline           $handler
     *
     * @return ContainerInterface
     */
    public function process(ContainerInterface $container, Pipeline $handler): ContainerInterface
    {
        /** @var Container $container */
        return $handler->handle(
            $container->withBindings($this->singletons(), [])
        );
    }
}
