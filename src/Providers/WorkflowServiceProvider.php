<?php

declare(strict_types=1);

namespace Workflow\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\SerializableClosure\SerializableClosure;
use Workflow\Commands\ActivityMakeCommand;
use Workflow\Commands\WorkflowMakeCommand;
use Workflow\Events\ActivityCompleted;
use Workflow\Events\ActivityFailed;
use Workflow\Events\ActivityStarted;
use Workflow\Events\WorkflowCompleted;
use Workflow\Events\WorkflowFailed;
use Workflow\Events\WorkflowStarted;
use Workflow\Listeners\MonitorActivityCompleted;
use Workflow\Listeners\MonitorActivityFailed;
use Workflow\Listeners\MonitorActivityStarted;
use Workflow\Listeners\MonitorWorkflowCompleted;
use Workflow\Listeners\MonitorWorkflowFailed;
use Workflow\Listeners\MonitorWorkflowStarted;

final class WorkflowServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! class_exists('Workflow\Models\Model')) {
            class_alias(config('workflows.base_model', Model::class), 'Workflow\Models\Model');
        }

        SerializableClosure::setSecretKey(config('app.key'));

        if (config('workflows.monitor', false)) {
            Event::listen(WorkflowStarted::class, [MonitorWorkflowStarted::class, 'handle']);
            Event::listen(WorkflowCompleted::class, [MonitorWorkflowCompleted::class, 'handle']);
            Event::listen(WorkflowFailed::class, [MonitorWorkflowFailed::class, 'handle']);
            Event::listen(ActivityStarted::class, [MonitorActivityStarted::class, 'handle']);
            Event::listen(ActivityCompleted::class, [MonitorActivityCompleted::class, 'handle']);
            Event::listen(ActivityFailed::class, [MonitorActivityFailed::class, 'handle']);
        }

        $this->publishes([
            __DIR__ . '/../config/workflows.php' => config_path('workflows.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../migrations/' => database_path('/migrations'),
        ], 'migrations');

        $this->commands([ActivityMakeCommand::class, WorkflowMakeCommand::class]);
    }

    public function register(): void
    {
        //
    }
}
