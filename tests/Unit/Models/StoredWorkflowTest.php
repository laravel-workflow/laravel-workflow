<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Illuminate\Support\Carbon;
use Tests\TestCase;
use Workflow\Models\StoredWorkflow;

final class StoredWorkflowTest extends TestCase
{
    public function testModel(): void
    {
        $workflow = StoredWorkflow::create([
            'class' => 'class',
            'status' => 'completed',
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

        $workflow->children()
            ->create([
                'class' => 'class',
                'status' => 'completed',
            ], [
                'parent_index' => 0,
                'parent_now' => now(),
            ]);

        $this->assertSame(1, $workflow->exceptions()->count());
        $this->assertSame(1, $workflow->logs()->count());
        $this->assertSame(1, $workflow->signals()->count());
        $this->assertSame(1, $workflow->timers()->count());
        $this->assertSame(1, $workflow->children()->count());

        Carbon::setTestNow(now()->addMonth()->addSecond());

        $this->artisan('model:prune', [
            '--model' => 'Workflow\Models\StoredWorkflow',
        ])
            ->doesntExpectOutputToContain('No prunable models found.')
            ->assertExitCode(0);

        $this->assertSame(0, $workflow->exceptions()->count());
        $this->assertSame(0, $workflow->logs()->count());
        $this->assertSame(0, $workflow->signals()->count());
        $this->assertSame(0, $workflow->timers()->count());
        $this->assertSame(0, $workflow->children()->count());
    }
}
