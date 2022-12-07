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

    public function uniqueId()
    {
        return $this->workflowId();
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

        try {
            return $this->{'execute'}(...$this->arguments);
        } catch (\Throwable $throwable) {
            $this->storedWorkflow->exceptions()
                ->create([
                    'class' => $this::class,
                    'exception' => serialize($throwable),
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
        $this->storedWorkflow->toWorkflow()
            ->fail($throwable);
    }

    public function heartbeat(): void
    {
        pcntl_alarm(max($this->timeout, 0));
    }
}
