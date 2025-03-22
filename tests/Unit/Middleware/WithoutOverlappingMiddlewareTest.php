<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\Repository;
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
        $this->app->make('cache')
            ->store()
            ->clear();

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
        $this->app->make('cache')
            ->store()
            ->clear();

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
        $this->app->make('cache')
            ->store()
            ->clear();

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

    public function testUnknownTypeDoesNotCallNext(): void
    {
        $this->app->make('cache')
            ->store()
            ->clear();

        $job = $this->mock(TestActivity::class, static function (MockInterface $mock) {
            $mock->shouldReceive('release')
                ->once();
        });

        $middleware = new WithoutOverlappingMiddleware(1, 999);

        $middleware->handle($job, function ($job) {
            $this->fail('Should not call next when type is unknown');
        });

        $this->assertNull(Cache::get($middleware->getWorkflowSemaphoreKey()));
        $this->assertNull(Cache::get($middleware->getActivitySemaphoreKey()));
    }

    public function testReleaseWhenCompareAndSetFails(): void
    {
        $this->app->make('cache')
            ->store()
            ->clear();

        $job = $this->mock(TestWorkflow::class, static function (MockInterface $mock) {
            $mock->shouldReceive('release')
                ->once();
        });

        $lock = $this->mock(Lock::class, static function (MockInterface $mock) {
            $mock->shouldReceive('get')
                ->once()
                ->andReturn(false);
        });

        $cache = $this->mock(Repository::class, static function (MockInterface $mock) use ($lock) {
            $mock->shouldReceive('lock')
                ->once()
                ->andReturn($lock);
            $mock->shouldReceive('get')
                ->andReturn([]);
        });

        $middleware = new WithoutOverlappingMiddleware(1, WithoutOverlappingMiddleware::WORKFLOW);

        $middleware->handle($job, function ($job) {
            $this->fail('Should not call next when lock is not acquired');
        });

        $this->assertNull(Cache::get($middleware->getWorkflowSemaphoreKey()));
        $this->assertNull(Cache::get($middleware->getActivitySemaphoreKey()));
    }
}
