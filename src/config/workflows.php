<?php

declare(strict_types=1);

return [
    'workflows_folder' => 'Workflows',

    'base_model' => Illuminate\Database\Eloquent\Model::class,

    'stored_workflow_model' => Workflow\Models\StoredWorkflow::class,

    'stored_workflow_exception_model' => Workflow\Models\StoredWorkflowException::class,

    'stored_workflow_log_model' => Workflow\Models\StoredWorkflowLog::class,

    'stored_workflow_signal_model' => Workflow\Models\StoredWorkflowSignal::class,

    'stored_workflow_timer_model' => Workflow\Models\StoredWorkflowTimer::class,

    'workflow_relationships_table' => 'workflow_relationships',

    'serializer' => Workflow\Serializers\Y::class,

    'prune_age' => '1 month',

    'webhooks_route' => env('WORKFLOW_WEBHOOKS_ROUTE', 'webhooks'),

    'webhook_auth' => [
        'method' => env('WORKFLOW_WEBHOOKS_AUTH_METHOD', 'none'),

        'signature' => [
            'header' => env('WORKFLOW_WEBHOOKS_SIGNATURE_HEADER', 'X-Signature'),
            'secret' => env('WORKFLOW_WEBHOOKS_SECRET'),
        ],

        'token' => [
            'header' => env('WORKFLOW_WEBHOOKS_TOKEN_HEADER', 'Authorization'),
            'token' => env('WORKFLOW_WEBHOOKS_TOKEN'),
        ],
    ],

    'monitor' => env('WORKFLOW_MONITOR', false),

    'monitor_url' => env('WORKFLOW_MONITOR_URL'),

    'monitor_api_key' => env('WORKFLOW_MONITOR_API_KEY'),

    'monitor_connection' => env('WORKFLOW_MONITOR_CONNECTION', config('queue.default')),

    'monitor_queue' => env(
        'WORKFLOW_MONITOR_QUEUE',
        config('queue.connections.' . config('queue.default') . '.queue', 'default')
    ),
];
