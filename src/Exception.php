<?php

declare(strict_types=1);

namespace Workflow;

use Workflow\Models\StoredWorkflow;

class Exception extends Activity
{
    public function __construct(
        public int $index,
        public string $now,
        public StoredWorkflow $storedWorkflow,
        public $exception,
    ) {
        parent::__construct($index, $now, $storedWorkflow);
    }

    public function handle()
    {
        if ($this->storedWorkflow->logs()->whereIndex($this->index)->exists()) {
            return;
        }

        return $this->exception;
    }
}
