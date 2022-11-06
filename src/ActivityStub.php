<?php

declare(strict_types=1);

namespace Workflow;

final class ActivityStub
{
    private $arguments;

    private function __construct(
        protected $activity,
        ...$arguments
    ) {
        $this->arguments = $arguments;
    }

    public static function make($activity, ...$arguments): static
    {
        return new self($activity, ...$arguments);
    }

    public function activity()
    {
        return $this->activity;
    }

    public function arguments()
    {
        return $this->arguments;
    }
}
