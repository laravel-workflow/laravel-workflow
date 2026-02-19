<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\Fixtures\TestActivity;
use Tests\Fixtures\TestChildWorkflow;
use Tests\Fixtures\TestOtherActivity;
use Tests\Fixtures\TestParentWorkflow;
use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\Signal;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class DynamicConnectionQueueTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::$queueConnection = 'workflows';
        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.redis.workflows', Config::get('database.redis.default'));
        Config::set('queue.connections.workflows', [
            'driver' => 'redis',
            'connection' => 'workflows',
            'queue' => 'workflows',
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ]);
    }

    public function testWorkWithDynamicConnectionQueue(): void
    {
        $workflow = WorkflowStub::make(TestWorkflow::class, 'workflows', 'workflows');

        $workflow->start(shouldAssert: false);

        $workflow->cancel();

        while (! $workflow->isCanceled());

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
        $this->assertSame([TestActivity::class, TestOtherActivity::class, Signal::class], $workflow->logs()
            ->pluck('class')
            ->sort()
            ->values()
            ->toArray());

        $this->assertSame('workflows', WorkflowStub::connection());
        $this->assertSame('workflows', WorkflowStub::queue());
    }

    public function testChildWorkflowWithDynamicConnectionQueue(): void
    {
        $workflow = WorkflowStub::make(TestParentWorkflow::class, 'workflows', 'workflows');

        $workflow->start();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
        $this->assertSame([TestActivity::class, TestChildWorkflow::class], $workflow->logs()
            ->pluck('class')
            ->sort()
            ->values()
            ->toArray());

        $this->assertSame('workflows', WorkflowStub::connection());
        $this->assertSame('workflows', WorkflowStub::queue());
        $this->assertDatabaseHas('workflows', [
            'class' => TestChildWorkflow::class,
            'queue_connection' => 'workflows',
            'queue' => 'workflows',
        ]);
    }
}
