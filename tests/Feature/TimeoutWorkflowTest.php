<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestTimeoutWorkflow;
use Tests\TestCase;
use Workflow\WorkflowStub;

final class TimeoutWorkflowTest extends TestCase
{
    public function testTimeout(): void
    {
        $workflow = WorkflowStub::make(TestTimeoutWorkflow::class);

        $workflow->start();

        while ($workflow->running());

        $this->assertSame(1, $workflow->exceptions()->count());
    }
}
