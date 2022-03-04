<?php

namespace Workflow\Providers;

use Illuminate\Support\ServiceProvider;

class WorkflowServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../migrations/' => database_path('/migrations')
        ], 'migrations');
    }

    public function register()
    {
        //
    }
}
