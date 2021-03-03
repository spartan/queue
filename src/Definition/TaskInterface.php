<?php

namespace Spartan\Queue\Definition;

/**
 * TaskInterface
 *
 * @package Spartan\Queue
 * @author  Iulian N. <iulian@spartanphp.com>
 * @license LICENSE MIT
 */
interface TaskInterface
{
    /**
     * Update task attributes
     *
     * @param mixed[] $attributes
     *
     * @return $this
     */
    public function withAttributes(array $attributes): self;

    /**
     * Get task attributes
     *
     * @return mixed[]
     */
    public function attributes(): array;

    /**
     * Check if task has failed
     *
     * @return bool
     */
    public function isFailed(): bool;

    /**
     * Check if task has finished
     *
     * @return bool
     */
    public function isFinished(): bool;

    /**
     * Mark this task as failed
     *
     * @return $this
     */
    public function markAsFailed(): self;

    /**
     * Mark this task as finished
     *
     * @return $this
     */
    public function markAsFinished(): self;

    /**
     * Run the task
     */
    public function __invoke(): void;
}
