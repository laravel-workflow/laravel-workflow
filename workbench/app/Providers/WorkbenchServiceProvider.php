<?php

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register MongoDB service provider if it exists and we're using MongoDB
        if (env('DB_CONNECTION') === 'mongodb' && class_exists(\MongoDB\Laravel\MongoDBServiceProvider::class)) {
            $this->app->register(\MongoDB\Laravel\MongoDBServiceProvider::class);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
