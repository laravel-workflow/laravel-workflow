<?php

declare(strict_types=1);

namespace Workflow\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

trait FetchesMonitorAuth
{
    protected function auth(): array
    {
        return Cache::remember('workflows.monitor_auth', 360, static function () {
            return Http::withToken(config('workflows.monitor_api_key'))
                ->get(config('workflows.monitor_url') . '/functions/v1/get-user')
                ->json();
        });
    }
}
