<?php

namespace Spartan\Queue\Task;

use Spartan\Queue\Definition\TaskInterface;
use Spartan\Queue\Definition\TaskTrait;

class Sleep implements TaskInterface
{
    use TaskTrait;

    protected int $seconds;

    /**
     * Sleep constructor.
     *
     * @param int $seconds
     */
    public function __construct(int $seconds = 4)
    {
        $this->seconds = $seconds;
    }

    /**
     * @throws \Exception
     */
    public function __invoke(): void
    {
        sleep($this->seconds);
    }
}
