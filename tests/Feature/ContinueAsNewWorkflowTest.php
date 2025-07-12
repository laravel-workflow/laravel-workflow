<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestContinueAsNewWorkflow;
use Tests\TestCase;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Serializer;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowContinuedStatus;
use Workflow\WorkflowStub;

final class ContinueAsNewWorkflowTest extends TestCase
{
    public function testCompleted(): void
    {
        $workflow = WorkflowStub::make(TestContinueAsNewWorkflow::class);

        $workflow->start();

        while ($workflow->running());

        $this->assertEquals(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertEquals('workflow_3', $workflow->output());

        $workflow = WorkflowStub::load(2);

        $this->assertEquals(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertEquals('workflow_3', $workflow->output());
    }

    public function testDeepContinueAsNewChain(): void
    {
        $chainLength = 1000;
        $workflows = [];

        for ($i = 0; $i < $chainLength; $i++) {
            $workflows[$i] = StoredWorkflow::create([
                'class' => TestContinueAsNewWorkflow::class,
                'status' => $i === $chainLength - 1
                    ? WorkflowCompletedStatus::class
                    : WorkflowContinuedStatus::class,
                'output' => $i === $chainLength - 1
                    ? Serializer::serialize('workflow_' . $chainLength)
                    : null,
            ]);
        }

        for ($i = 0; $i < $chainLength - 1; $i++) {
            $workflows[$i + 1]->parents()->attach($workflows[$i], [
                'parent_index' => StoredWorkflow::CONTINUE_PARENT_INDEX,
                'parent_now' => now(),
            ]);
        }

        $originalWorkflow = WorkflowStub::load($workflows[0]->id);

        $startTime = microtime(true);
        $status = $originalWorkflow->status();
        $endTime = microtime(true);

        $this->assertEquals(WorkflowCompletedStatus::class, $status);
        $this->assertLessThan(
            3.0,
            $endTime - $startTime,
            'Chain resolution should complete in less than 3 seconds even for 1000 workflows'
        );

        $startTime = microtime(true);
        $output = $originalWorkflow->output();
        $endTime = microtime(true);

        $this->assertEquals('workflow_1000', $output);
        $this->assertLessThan(
            3.0,
            $endTime - $startTime,
            'Output resolution should complete in less than 3 seconds even for 1000 workflows'
        );
    }
}
