<?php

declare(strict_types=1);

namespace Workflow;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Workflow\Models\StoredWorkflow;

final class Signal implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 0;

    public int $maxExceptions = 0;

    public function __construct(
        public StoredWorkflow $storedWorkflow
    ) {
    }

    public function middleware()
    {
        return [
            (new WithoutOverlapping("workflow:{$this->storedWorkflow->id}"))->shared(),
        ];
    }

    public function handle(): void
    {
        $workflow = $this->storedWorkflow->toWorkflow();

        try {
            $workflow->resume();
        } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound) {
            if ($workflow->running()) {
                $this->release();
            }
        }
    }
}
