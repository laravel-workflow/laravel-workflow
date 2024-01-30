<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Fixtures\TestSimpleWorkflow;
use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class DispatchWorkflowInTransactionTest extends TestCase
{
    public function testRaceCondition(): void
    {
        $workflow = null;
        $start = now();

        DB::transaction(static function () use (&$workflow) {
            $workflow = WorkflowStub::make(TestSimpleWorkflow::class);

            /**
             * use case: link the workflow to an entity in the database
             *
             * EntityWorkflow::create(["entity_id" => 1, "workflow_id" => $workflow->id()])
             */

            /**
             * dispatch the workflow to the queue
             */
            $workflow->start();

            /**
             * pretend that committing the transaction takes a while
             */
            sleep(3);
        });

        /**
         * the workflow stays in the pending state as the transaction was not committed
         * when the worker was told to process the workflow
         *
         * the exception is silently caught in src/Workflow.php:115
         */
        while ($workflow->running() && $workflow->exceptions()->isEmpty() && now()->diffInSeconds($start) < 15);

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertEmpty($workflow->exceptions());
    }
}
