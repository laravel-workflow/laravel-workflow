<?php

declare(strict_types=1);

namespace Tests\Unit;

use BadMethodCallException;
use Exception;
use Tests\Fixtures\NonRetryableTestExceptionActivity;
use Tests\Fixtures\TestExceptionActivity;
use Tests\Fixtures\TestInvalidActivity;
use Tests\Fixtures\TestOtherActivity;
use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\Exceptions\NonRetryableException;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Serializer;
use Workflow\States\WorkflowCreatedStatus;
use Workflow\States\WorkflowFailedStatus;
use Workflow\WorkflowStub;

final class ActivityTest extends TestCase
{
    public function testActivity(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $activity = new TestOtherActivity(0, now()->toDateTimeString(), StoredWorkflow::findOrFail($workflow->id()), [
            'other',
        ]);
        $activity->timeout = 1;
        $activity->heartbeat();

        $result = $activity->handle();

        $this->assertSame(['other'], $result);
        $this->assertSame([1, 2, 5, 10, 15, 30, 60, 120], $activity->backoff());
        $this->assertSame($workflow->id(), $activity->workflowId());
        $this->assertSame($activity->timeout, pcntl_alarm(0));
    }

    public function testInvalidActivity(): void
    {
        $this->expectException(BadMethodCallException::class);

        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $activity = new TestInvalidActivity(0, now()->toDateTimeString(), StoredWorkflow::findOrFail($workflow->id()));

        $activity->handle();
    }

    public function testExceptionActivity(): void
    {
        $this->expectException(Exception::class);

        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $activity = new TestExceptionActivity(0, now()->toDateTimeString(), StoredWorkflow::findOrFail(
            $workflow->id()
        ));

        $activity->handle();

        $workflow->fresh();

        $this->assertSame(1, $workflow->exceptions()->count());
        $this->assertSame(0, $workflow->logs()->count());
        $this->assertSame(WorkflowFailedStatus::class, $workflow->status());
    }

    public function testNonRetryableExceptionActivity(): void
    {
        $this->expectException(NonRetryableException::class);

        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $activity = new NonRetryableTestExceptionActivity(0, now()->toDateTimeString(), StoredWorkflow::findOrFail(
            $workflow->id()
        ));

        $activity->handle();

        $workflow->fresh();

        $this->assertSame(1, $workflow->exceptions()->count());
        $this->assertSame(0, $workflow->logs()->count());
        $this->assertSame(WorkflowFailedStatus::class, $workflow->status());
    }

    public function testFailedActivity(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $activity = new TestExceptionActivity(0, now()->toDateTimeString(), StoredWorkflow::findOrFail(
            $workflow->id()
        ));

        $activity->failed(new Exception('failed'));

        $workflow->fresh();

        $this->assertSame(0, $workflow->exceptions()->count());
        $this->assertSame(0, $workflow->logs()->count());
        $this->assertSame(WorkflowCreatedStatus::class, $workflow->status());
    }

    public function testActivityAlreadyComplete(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        StoredWorkflow::findOrFail($workflow->id())->logs()->create([
            'index' => 0,
            'now' => now(),
            'class' => TestOtherActivity::class,
            'result' => Serializer::serialize('other'),
        ]);
        $activity = new TestOtherActivity(0, now()->toDateTimeString(), StoredWorkflow::findOrFail($workflow->id()), [
            'other',
        ]);
        $activity->timeout = 1;
        $activity->heartbeat();

        $result = $activity->handle();

        $this->assertNull($result);
        $this->assertSame([1, 2, 5, 10, 15, 30, 60, 120], $activity->backoff());
        $this->assertSame($workflow->id(), $activity->workflowId());
        $this->assertSame($activity->timeout, pcntl_alarm(0));
    }

    public function testWebhookUrl(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $activity = new TestOtherActivity(0, now()->toDateTimeString(), StoredWorkflow::findOrFail($workflow->id()), [
            'other',
        ]);

        $this->assertSame('http://localhost/webhooks/test-workflow', $activity->webhookUrl());
        $this->assertSame('http://localhost/webhooks/signal/1/other', $activity->webhookUrl('other'));
    }
}
