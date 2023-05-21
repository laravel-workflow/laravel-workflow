<?php

declare(strict_types=1);

namespace Workflow;

use BadMethodCallException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Workflow\Middleware\WithoutOverlappingMiddleware;
use Workflow\Middleware\WorkflowMiddleware;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Y;

class Activity implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = PHP_INT_MAX;

    public $maxExceptions = PHP_INT_MAX;

    public $timeout = 0;

    public $arguments;

    public function __construct(
        public int $index,
        public string $now,
        public StoredWorkflow $storedWorkflow,
        ...$arguments
    ) {
        $this->arguments = $arguments;

        if (property_exists($this, 'connection')) {
            $this->onConnection($this->connection);
        }

        if (property_exists($this, 'queue')) {
            $this->onQueue($this->queue);
        }
    }

    public function backoff()
    {
        return [1, 2, 5, 10, 15, 30, 60, 120];
    }

    public function workflowId()
    {
        return $this->storedWorkflow->id;
    }

    public function handle()
    {
        if (! method_exists($this, 'execute')) {
            throw new BadMethodCallException('Execute method not implemented.');
        }

        if ($this->storedWorkflow->logs()->whereIndex($this->index)->exists()) {
            return;
        }

        try {
            return $this->{'execute'}(...$this->arguments);
        } catch (\Throwable $throwable) {
            $this->storedWorkflow->exceptions()
                ->create([
                    'class' => $this::class,
                    'exception' => Y::serialize($throwable),
                ]);

            throw $throwable;
        }
    }

    public function middleware()
    {
        return [
            new WithoutOverlappingMiddleware($this->storedWorkflow->id, WithoutOverlappingMiddleware::ACTIVITY),
            new WorkflowMiddleware(),
        ];
    }

    public function failed(Throwable $throwable): void
    {
        $workflow = $this->storedWorkflow->toWorkflow();

        $throwable = [
            'class' => get_class($throwable),
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'line' => $throwable->getLine(),
            'file' => $throwable->getFile(),
            'trace' => collect($throwable->getTrace())
                ->filter(static fn ($trace) => Y::serializable($trace))
                ->toArray(),
        ];

        Exception::dispatch(
            $this->index,
            $this->now,
            $this->storedWorkflow,
            $throwable,
            $workflow->connection(),
            $workflow->queue()
        );
    }

    public function heartbeat(): void
    {
        pcntl_alarm(max($this->timeout, 0));
    }
}
