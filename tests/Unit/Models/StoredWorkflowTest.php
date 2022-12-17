<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Tests\TestCase;
use Workflow\Models\StoredWorkflow;
use Workflow\Models\StoredWorkflowException;
use Workflow\Models\StoredWorkflowLog;
use Workflow\Models\StoredWorkflowSignal;
use Workflow\Models\StoredWorkflowTimer;

final class StoredWorkflowTest extends TestCase
{
    public function testModel(): void
    {
        $workflow = StoredWorkflow::create([
            'class' => 'class',
        ]);

        $workflow->exceptions()
            ->create([
                'class' => 'class',
                'exception' => 'exception',
            ]);

        $workflow->logs()
            ->create([
                'index' => 0,
                'now' => now(),
                'class' => 'class',
            ]);

        $workflow->signals()
            ->create([
                'method' => 'method',
            ]);

        $workflow->timers()
            ->create([
                'index' => 0,
                'stop_at' => now(),
            ]);

        $exception = $workflow->exceptions()
            ->first();
        $this->assertSame(1, $workflow->exceptions()->count());
        $this->assertSame($workflow->id, $exception->stored_workflow_id);
        $this->assertSame($exception->id, StoredWorkflowException::whereStoredWorkflowId($workflow->id)->first()->id);

        $log = $workflow->logs()
            ->first();
        $this->assertSame(1, $workflow->logs()->count());
        $this->assertSame($workflow->id, $log->stored_workflow_id);
        $this->assertSame($log->id, StoredWorkflowLog::whereStoredWorkflowId($workflow->id)->first()->id);

        $signal = $workflow->signals()
            ->first();
        $this->assertSame(1, $workflow->signals()->count());
        $this->assertSame($workflow->id, $signal->stored_workflow_id);
        $this->assertSame($signal->id, StoredWorkflowSignal::whereStoredWorkflowId($workflow->id)->first()->id);

        $timer = $workflow->timers()
            ->first();
        $this->assertSame(1, $workflow->timers()->count());
        $this->assertSame($workflow->id, $timer->stored_workflow_id);
        $this->assertSame($timer->id, StoredWorkflowTimer::whereStoredWorkflowId($workflow->id)->first()->id);
    }
}
