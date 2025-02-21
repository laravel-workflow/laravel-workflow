<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Contracts\Foundation\Application;
use Workflow\ActivityStub;
use Workflow\QueryMethod;
use Workflow\SignalMethod;
use Workflow\Webhook;
use Workflow\Workflow;
use Workflow\WorkflowStub;

#[Webhook]
class TestWebhookWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    private bool $canceled = false;

    #[SignalMethod]
    #[Webhook]
    public function cancel(): void
    {
        $this->canceled = true;
    }

    #[QueryMethod]
    public function isCanceled(): bool
    {
        return $this->canceled;
    }

    public function execute(Application $app, $shouldAssert = false)
    {
        assert($app->runningInConsole());

        if ($shouldAssert) {
            assert(! $this->canceled);
        }

        $otherResult = yield ActivityStub::make(TestOtherActivity::class, 'other');

        if ($shouldAssert) {
            assert(! $this->canceled);
        }

        yield WorkflowStub::await(fn (): bool => $this->canceled);

        $result = yield ActivityStub::make(TestActivity::class);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
