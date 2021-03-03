<?php

namespace Spartan\Queue\Task;

use Spartan\Queue\Definition\TaskInterface;
use Spartan\Queue\Definition\TaskTrait;

/**
 * Consumer Task
 *
 * @package Spartan\Queue
 * @author  Iulian N. <iulian@spartanphp.com>
 * @license LICENSE MIT
 */
class Consumer implements TaskInterface
{
    use TaskTrait;

    protected int $count;

    /**
     * Consumer constructor.
     *
     * @param int $count
     */
    public function __construct(int $count = 1)
    {
        $this->count = $count;
    }

    public function __invoke(): void
    {
        // does nothing
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->count;
    }
}
