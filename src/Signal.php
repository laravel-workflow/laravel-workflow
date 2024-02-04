<?php

declare(strict_types=1);

namespace Workflow;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\ModelStates\Exceptions\TransitionNotFound;
use Workflow\Middleware\WithoutOverlappingMiddleware;
use Workflow\Models\StoredWorkflow;

final class Signal implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 0;

    public int $maxExceptions = 0;

    /**
     * @param StoredWorkflow<Workflow, null> $storedWorkflow
     * @param string|null $connection
     * @param string|null $queue
     */
    public function __construct(
        public StoredWorkflow $storedWorkflow,
        ?string $connection = null,
        ?string $queue = null
    ) {
        $connection = $connection ?? config('queue.default');
        $queue = $queue ?? config('queue.connections.' . $connection . '.queue', 'default');
        $this->onConnection($connection);
        $this->onQueue($queue);
    }

    /**
     * @return mixed[]
     * @throws BindingResolutionException
     */
    public function middleware()
    {
        return [
            new WithoutOverlappingMiddleware($this->storedWorkflow->id, WithoutOverlappingMiddleware::WORKFLOW),
        ];
    }

    public function handle(): void
    {
        $workflow = $this->storedWorkflow->toWorkflow();

        try {
            $workflow->resume();
        } catch (TransitionNotFound) {
            if ($workflow->running()) {
                $this->release();
            }
        }
    }
}
