<?php

declare(strict_types=1);

namespace Workflow\Providers;

use Illuminate\Support\ServiceProvider;

final class WorkflowServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../migrations/' => database_path('/migrations'),
        ], 'migrations');
    }

    public function register(): void
    {
        //
    }
}
