<?php

namespace Workflow;

class ActivityStub
{
    protected $activity;

    protected $arguments;

    private function __construct($activity, ...$arguments)
    {
        $this->activity = $activity;
        $this->arguments = $arguments;
    }

    public static function make($activity, ...$arguments)
    {
        return new static($activity, ...$arguments);
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
