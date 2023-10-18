<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Tests\Fixtures\TestActivity;
use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\Middleware\WithoutOverlappingMiddleware;

final class WithoutOverlappingMiddlewareTest extends TestCase
{
    public function testMiddleware(): void
    {
        $middleware = new WithoutOverlappingMiddleware(1, WithoutOverlappingMiddleware::WORKFLOW);
        $this->assertSame($middleware->getLockKey(), 'laravel-workflow-overlap:1');
        $this->assertSame($middleware->getWorkflowSemaphoreKey(), 'laravel-workflow-overlap:1:workflow');
        $this->assertSame($middleware->getActivitySemaphoreKey(), 'laravel-workflow-overlap:1:activity');

        $middleware = new WithoutOverlappingMiddleware(1, WithoutOverlappingMiddleware::ACTIVITY);
        $this->assertSame($middleware->getLockKey(), 'laravel-workflow-overlap:1');
        $this->assertSame($middleware->getWorkflowSemaphoreKey(), 'laravel-workflow-overlap:1:workflow');
        $this->assertSame($middleware->getActivitySemaphoreKey(), 'laravel-workflow-overlap:1:activity');
    }

    public function testAllowsOnlyOneWorkflowInstance(): void
    {
        $workflow1 = $this->mock(TestWorkflow::class);
        $middleware1 = new WithoutOverlappingMiddleware(1, WithoutOverlappingMiddleware::WORKFLOW);

        $workflow2 = $this->mock(TestWorkflow::class, static function (MockInterface $mock) {
            $mock->shouldReceive('release')
                ->once();
        });
        $middleware2 = new WithoutOverlappingMiddleware(1, WithoutOverlappingMiddleware::WORKFLOW);

        $activity = $this->mock(TestActivity::class, static function (MockInterface $mock) {
            $mock->shouldReceive('release')
                ->once();
        });
        $middleware3 = new WithoutOverlappingMiddleware(1, WithoutOverlappingMiddleware::ACTIVITY);

        $middleware1->handle($workflow1, function ($job) use (
            $middleware1,
            $workflow2,
            $middleware2,
            $activity,
            $middleware3
        ) {
            $this->assertSame(1, Cache::get($middleware1->getWorkflowSemaphoreKey()));
            $this->assertNull(Cache::get($middleware1->getActivitySemaphoreKey()));

            $middleware2->handle($workflow2, static function ($job) {
            });
            $middleware3->handle($activity, static function ($job) {
            });
        });

        $this->assertSame(0, Cache::get($middleware1->getWorkflowSemaphoreKey()));
        $this->assertNull(Cache::get($middleware1->getActivitySemaphoreKey()));
    }

    public function testAllowsMultipleActivityInstances(): void
    {
        $activity1 = $this->mock(TestActivity::class);
        $middleware1 = new WithoutOverlappingMiddleware(1, WithoutOverlappingMiddleware::ACTIVITY);

        $activity2 = $this->mock(TestActivity::class);
        $middleware2 = new WithoutOverlappingMiddleware(1, WithoutOverlappingMiddleware::ACTIVITY);

        $workflow1 = $this->mock(TestWorkflow::class, static function (MockInterface $mock) {
            $mock->shouldReceive('release')
                ->once();
        });
        $middleware3 = new WithoutOverlappingMiddleware(1, WithoutOverlappingMiddleware::WORKFLOW);

        $middleware1->handle($activity1, function ($job) use (
            $middleware1,
            $activity2,
            $middleware2,
            $workflow1,
            $middleware3
        ) {
            $this->assertNull(Cache::get($middleware1->getWorkflowSemaphoreKey()));
            $this->assertSame(1, count(Cache::get($middleware1->getActivitySemaphoreKey())));

            $middleware2->handle($activity2, function ($job) use ($middleware2) {
                $this->assertNull(Cache::get($middleware2->getWorkflowSemaphoreKey()));
                $this->assertSame(2, count(Cache::get($middleware2->getActivitySemaphoreKey())));
            });

            $middleware3->handle($workflow1, static function ($job) {
            });
        });

        $this->assertNull(Cache::get($middleware1->getWorkflowSemaphoreKey()));
        $this->assertSame(0, count(Cache::get($middleware1->getActivitySemaphoreKey())));
    }
}
