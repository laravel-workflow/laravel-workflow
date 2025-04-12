<?php

declare(strict_types=1);

namespace Workflow\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Laravel\SerializableClosure\SerializableClosure;
use Workflow\Commands\ActivityMakeCommand;
use Workflow\Commands\WorkflowMakeCommand;

final class WorkflowServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! class_exists('Workflow\Models\Model')) {
            class_alias(config('workflows.base_model', Model::class), 'Workflow\Models\Model');
        }

        SerializableClosure::setSecretKey(config('app.key'));

        $this->publishes([
            __DIR__ . '/../config/workflows.php' => config_path('workflows.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../migrations/' => database_path('/migrations'),
        ], 'migrations');

        $this->commands([ActivityMakeCommand::class, WorkflowMakeCommand::class]);
    }
}
