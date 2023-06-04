<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestContinuesWorkflow;
use Tests\Fixtures\TestOtherActivity;
use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class ContinuesWorkflowTest extends TestCase
{
    public function testCompleted(): void
    {
        $workflow = WorkflowStub::make(TestContinuesWorkflow::class);
        $id = $workflow->id();
        $uuid = $workflow->uuid();
        $workflow->start();

        while (WorkflowStub::search($uuid)->count() < 5);

        $workflows = WorkflowStub::search($uuid);
        $workflows->each(static function ($workflow) {
            while ($workflow->running());
        });
        $workflows = WorkflowStub::search($uuid);

        $this->assertSame($id, $workflow->id());
        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertNull($workflow->output());
        $this->assertSame([TestOtherActivity::class, TestOtherActivity::class], $workflow->logs()
            ->pluck('class')
            ->sort()
            ->values()
            ->toArray());
        $this->assertSame(['9', '10'], $workflows->get(4)->output());
        $this->assertSame([TestOtherActivity::class, TestOtherActivity::class], $workflows->get(4)->logs()
            ->pluck('class')
            ->sort()
            ->values()
            ->toArray());
    }
}
