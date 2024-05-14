<?php

declare(strict_types=1);

namespace Workflow;

use Workflow\Models\StoredWorkflow;

class ChildWorkflow extends Activity
{
    public function __construct(
        public int $index,
        public string $now,
        public StoredWorkflow $storedWorkflow,
        public $return,
        public StoredWorkflow $parentWorkflow,
        $connection = null,
        $queue = null
    ) {
        $connection = $connection ?? config('queue.default');
        $queue = $queue ?? config('queue.connections.' . $connection . '.queue', 'default');
        $this->onConnection($connection);
        $this->onQueue($queue);
    }

    public function handle()
    {
        $workflow = $this->parentWorkflow->toWorkflow();

        try {
            if ($this->parentWorkflow->logs()->whereIndex($this->index)->exists()) {
                $workflow->resume();
            } else {
                $workflow->next($this->index, $this->now, $this->storedWorkflow->class, $this->return);
            }
        } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound) {
            if ($workflow->running()) {
                $this->release();
            }
        }

        return $this->return;
    }
}
