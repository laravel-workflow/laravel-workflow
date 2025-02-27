<?php

declare(strict_types=1);

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Symfony\Component\Process\Process;

abstract class TestCase extends BaseTestCase
{
    public const NUMBER_OF_WORKERS = 2;

    private static $workers = [];

    public static function setUpBeforeClass(): void
    {
        for ($i = 0; $i < self::NUMBER_OF_WORKERS; $i++) {
            self::$workers[$i] = new Process([
                'php',
                __DIR__ . '/../vendor/orchestra/testbench-core/laravel/artisan',
                'queue:work',
            ]);
            self::$workers[$i]->start();
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$workers as $worker) {
            $worker->stop();
        }
    }

    protected function setUp(): void
    {
        if (TestSuiteSubscriber::getCurrentSuite() === 'feature') {
            putenv('APP_KEY=base64:i3g6f+dV8FfsIkcxqd7gbiPn2oXk5r00sTmdD6V5utI=');
            putenv('DB_CONNECTION=pgsql');
            putenv('DB_HOST=db');
            putenv('DB_PORT=5432');
            putenv('DB_DATABASE=laravel');
            putenv('DB_USERNAME=laravel');
            putenv('DB_PASSWORD=laravel');

            putenv('REDIS_HOST=redis');
            putenv('REDIS_PASSWORD=null');
            putenv('REDIS_PORT=6379');

            putenv('QUEUE_CONNECTION=redis');
        }

        if (TestSuiteSubscriber::getCurrentSuite() === 'unit') {
            putenv('APP_KEY=base64:i3g6f+dV8FfsIkcxqd7gbiPn2oXk5r00sTmdD6V5utI=');
            putenv('DB_CONNECTION=pgsql');
            putenv('DB_HOST=db');
            putenv('DB_PORT=5432');
            putenv('DB_DATABASE=laravel');
            putenv('DB_USERNAME=laravel');
            putenv('DB_PASSWORD=laravel');

            putenv('QUEUE_CONNECTION=sync');
        }

        parent::setUp();
    }

    protected function defineDatabaseMigrations()
    {
        $this->artisan('migrate:fresh', [
            '--path' => dirname(__DIR__) . '/src/migrations',
            '--realpath' => true,
        ]);

        $this->loadLaravelMigrations();
    }

    protected function getPackageProviders($app)
    {
        return [\Workflow\Providers\WorkflowServiceProvider::class];
    }
}
