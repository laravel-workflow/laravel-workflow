<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Workflow\Events\ActivityCompleted;
use Workflow\Events\ActivityFailed;
use Workflow\Events\ActivityStarted;
use Workflow\Events\WorkflowCompleted;
use Workflow\Events\WorkflowFailed;
use Workflow\Events\WorkflowStarted;
use Workflow\Providers\WorkflowServiceProvider;

final class WorkflowServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->register(WorkflowServiceProvider::class);
    }

    public function testProviderLoads(): void
    {
        $this->assertTrue(
            $this->app->getProvider(WorkflowServiceProvider::class) instanceof WorkflowServiceProvider
        );
    }

    public function testEventListenersAreRegistered(): void
    {
        config([
            'workflows.monitor' => true,
        ]);

        (new WorkflowServiceProvider($this->app))->boot();

        $dispatcher = app('events');

        $expectedListeners = [
            WorkflowStarted::class => \Workflow\Listeners\MonitorWorkflowStarted::class,
            WorkflowCompleted::class => \Workflow\Listeners\MonitorWorkflowCompleted::class,
            WorkflowFailed::class => \Workflow\Listeners\MonitorWorkflowFailed::class,
            ActivityStarted::class => \Workflow\Listeners\MonitorActivityStarted::class,
            ActivityCompleted::class => \Workflow\Listeners\MonitorActivityCompleted::class,
            ActivityFailed::class => \Workflow\Listeners\MonitorActivityFailed::class,
        ];

        foreach ($expectedListeners as $event => $listener) {
            $registeredListeners = $dispatcher->getListeners($event);

            $attached = false;
            foreach ($registeredListeners as $registeredListener) {
                if ($registeredListener instanceof \Closure) {
                    $closureReflection = new \ReflectionFunction($registeredListener);
                    $useVariables = $closureReflection->getStaticVariables();

                    if (isset($useVariables['listener']) && is_array($useVariables['listener'])) {
                        if ($useVariables['listener'][0] === $listener) {
                            $attached = true;
                            break;
                        }
                    }
                }
            }

            $this->assertTrue($attached, "Event [{$event}] does not have the [{$listener}] listener attached.");
        }
    }

    public function testConfigIsPublished(): void
    {
        Artisan::call('vendor:publish', [
            '--tag' => 'config',
        ]);

        $this->assertFileExists(config_path('workflows.php'));
    }

    public function testMigrationsArePublished(): void
    {
        Artisan::call('vendor:publish', [
            '--tag' => 'migrations',
        ]);

        $migrationFiles = glob(database_path('migrations/*.php'));
        $this->assertNotEmpty($migrationFiles, 'Migrations should be published');
    }

    public function testCommandsAreRegistered(): void
    {
        $registeredCommands = array_keys(Artisan::all());

        $expectedCommands = ['make:activity', 'make:workflow'];

        foreach ($expectedCommands as $command) {
            $this->assertContains(
                $command,
                $registeredCommands,
                "Command [{$command}] is not registered in Artisan."
            );
        }
    }
}
