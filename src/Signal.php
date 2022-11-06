<?php

declare(strict_types=1);

namespace Workflow;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Workflow\Models\StoredWorkflow;

final class Signal implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public function __construct(
        public StoredWorkflow $storedWorkflow
    ) {
    }

    public function handle(): void
    {
        $workflow = $this->storedWorkflow->toWorkflow();

        if ($workflow->running()) {
            try {
                $workflow->resume();
            } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound) {
                $this->release();
            }
        }
    }
}
