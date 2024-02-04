<?php

declare(strict_types=1);

namespace Workflow;

use Workflow\Models\StoredWorkflow;

/**
 * @extends Activity<Workflow, mixed>
 */
class Exception extends Activity
{
    /**
     * @param int $index
     * @param string $now
     * @param StoredWorkflow<Workflow, null> $storedWorkflow
     * @param array{class: string, message: string, code: int|string, line: int, file: string, trace: mixed[], snippet: string[]} $exception
     *
     * @TODO: check if this class really must extend the Activity class. This makes typing more difficult.
     *
     */
    public function __construct(
        public int $index,
        public string $now,
        public StoredWorkflow $storedWorkflow, // @phpstan-ignore-line
        public array $exception,
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
