<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

final class WorkflowsConfigTest extends TestCase
{
    public function testConfigIsLoaded(): void
    {
        $this->app->make('config')
            ->set('queue', require config_path('queue.php'));
        $this->app->make('config')
            ->set('workflows', require config_path('workflows.php'));

        $this->assertNotEmpty(config('workflows'), 'The workflows config file is not loaded.');

        $expectedConfig = [
            'workflows_folder' => 'Workflows',
            'base_model' => \Illuminate\Database\Eloquent\Model::class,
            'stored_workflow_model' => \Workflow\Models\StoredWorkflow::class,
            'stored_workflow_exception_model' => \Workflow\Models\StoredWorkflowException::class,
            'stored_workflow_log_model' => \Workflow\Models\StoredWorkflowLog::class,
            'stored_workflow_signal_model' => \Workflow\Models\StoredWorkflowSignal::class,
            'stored_workflow_timer_model' => \Workflow\Models\StoredWorkflowTimer::class,
            'workflow_relationships_table' => 'workflow_relationships',
            'serializer' => \Workflow\Serializers\Y::class,
            'prune_age' => '1 month',
            'webhooks_route' => env('WORKFLOW_WEBHOOKS_ROUTE', 'webhooks'),
            'monitor' => env('WORKFLOW_MONITOR', false),
            'monitor_url' => env('WORKFLOW_MONITOR_URL'),
            'monitor_api_key' => env('WORKFLOW_MONITOR_API_KEY'),
            'monitor_connection' => env('WORKFLOW_MONITOR_CONNECTION', config('queue.default')),
            'monitor_queue' => env(
                'WORKFLOW_MONITOR_QUEUE',
                config('queue.connections.' . config('queue.default') . '.queue', 'default')
            ),
        ];

        foreach ($expectedConfig as $key => $expectedValue) {
            $this->assertTrue(config()->has("workflows.{$key}"), "The config key [workflows.{$key}] is missing.");

            $this->assertEquals(
                $expectedValue,
                config("workflows.{$key}"),
                "The config key [workflows.{$key}] does not match the expected value."
            );
        }
    }
}
