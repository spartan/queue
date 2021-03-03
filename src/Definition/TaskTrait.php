<?php

namespace Spartan\Queue\Definition;

trait TaskTrait
{
    /**
     * @var mixed[]
     */
    protected array $attributes = [];

    protected bool $isFinished = false;

    protected bool $isFailed = false;

    /**
     * {@inheritDoc}
     */
    public function withAttributes(array $attributes): self
    {
        $this->attributes = $attributes + $this->attributes;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * {@inheritDoc}
     */
    public function isFailed(): bool
    {
        return $this->isFailed;
    }

    /**
     * {@inheritDoc}
     */
    public function isFinished(): bool
    {
        return $this->isFinished;
    }

    /**
     * {@inheritDoc}
     */
    public function markAsFailed(): self
    {
        $this->isFailed = true;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function markAsFinished(): self
    {
        $this->isFinished = true;

        return $this;
    }
}
