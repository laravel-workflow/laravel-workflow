<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestParentSignalingChildViaSignal;
use Tests\Fixtures\TestParentWorkflowSignalingChildDirectly;
use Tests\Fixtures\TestParentWorkflowWithContextCheck;
use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class ChildWorkflowSignalingTest extends TestCase
{
    public function testParentCanSignalChildDirectly(): void
    {
        $parentWorkflow = WorkflowStub::make(TestParentWorkflowSignalingChildDirectly::class);
        $parentWorkflow->start();

        while ($parentWorkflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $parentWorkflow->status());
        $this->assertSame('direct_signaling_approved', $parentWorkflow->output());
    }

    public function testParentContextNotCorruptedByChildSignaling(): void
    {
        $parentWorkflow = WorkflowStub::make(TestParentWorkflowWithContextCheck::class);
        $parentWorkflow->start();

        while ($parentWorkflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $parentWorkflow->status());
        $this->assertSame('success', $parentWorkflow->output());
    }

    public function testParentSignalMethodForwardsToChild(): void
    {
        $parentWorkflow = WorkflowStub::make(TestParentSignalingChildViaSignal::class);
        $parentWorkflow->start();

        sleep(3);

        $parentWorkflow->forwardApproval('approved');

        $timeout = 10;
        while ($parentWorkflow->running() && $timeout-- > 0) {
            sleep(1);
        }

        if ($timeout <= 0) {
            echo 'Parent status: ' . $parentWorkflow->status() . "\n";
            echo 'Parent output: ' . ($parentWorkflow->output() ?? 'null') . "\n";
            $children = \Workflow\Models\StoredWorkflow::find(1)->children()->get();
            foreach ($children as $child) {
                echo "Child {$child->id} status: {$child->status}\n";
            }
        }

        $this->assertSame(WorkflowCompletedStatus::class, $parentWorkflow->status());
        $this->assertSame('forwarded_approved', $parentWorkflow->output());
    }
}
