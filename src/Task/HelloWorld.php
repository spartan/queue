<?php

namespace Spartan\Queue\Task;

use Spartan\Queue\Definition\TaskInterface;
use Spartan\Queue\Definition\TaskTrait;

class HelloWorld implements TaskInterface
{
    use TaskTrait;

    /**
     * @throws \Exception
     */
    public function __invoke(): void
    {
        echo "Hello World!";
    }
}
