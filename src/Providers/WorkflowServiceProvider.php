<?php

declare(strict_types=1);

namespace Workflow\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\SerializableClosure\SerializableClosure;

final class WorkflowServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        SerializableClosure::setSecretKey(config('app.key'));

        $this->publishes([
            __DIR__ . '/../config/workflows.php' => config_path('workflows.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../migrations/' => database_path('/migrations'),
        ], 'migrations');
    }

    public function register(): void
    {
        //
    }
}
