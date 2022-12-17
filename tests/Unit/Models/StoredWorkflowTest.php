<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Tests\TestCase;
use Workflow\Models\StoredWorkflow;

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

        $this->assertSame(1, $workflow->exceptions()->count());
        $this->assertSame(1, $workflow->logs()->count());
        $this->assertSame(1, $workflow->signals()->count());
        $this->assertSame(1, $workflow->timers()->count());
    }
}
