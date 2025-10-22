<?php

declare(strict_types=1);

namespace Tests;

use Dotenv\Dotenv;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Symfony\Component\Process\Process;

abstract class TestCase extends BaseTestCase
{
    public const NUMBER_OF_WORKERS = 2;

    private static $workers = [];

    public static function setUpBeforeClass(): void
    {
        if (getenv('GITHUB_ACTIONS') !== 'true') {
            if (TestSuiteSubscriber::getCurrentSuite() === 'feature') {
                Dotenv::createImmutable(__DIR__, '.env.feature')->safeLoad();
            } elseif (TestSuiteSubscriber::getCurrentSuite() === 'unit') {
                Dotenv::createImmutable(__DIR__, '.env.unit')->safeLoad();
            }
        }

        for ($i = 0; $i < self::NUMBER_OF_WORKERS; $i++) {
            self::$workers[$i] = new Process(['php', __DIR__ . '/../vendor/bin/testbench', 'queue:work']);
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
        if (getenv('GITHUB_ACTIONS') !== 'true') {
            if (TestSuiteSubscriber::getCurrentSuite() === 'feature') {
                Dotenv::createImmutable(__DIR__, '.env.feature')->safeLoad();
            } elseif (TestSuiteSubscriber::getCurrentSuite() === 'unit') {
                Dotenv::createImmutable(__DIR__, '.env.unit')->safeLoad();
            }
        }

        parent::setUp();
    }

    protected function defineDatabaseMigrations()
    {
        if (env('DB_CONNECTION') !== 'mongodb') {
            $this->artisan('migrate:fresh', [
                '--path' => dirname(__DIR__) . '/src/migrations',
                '--realpath' => true,
            ]);

            $this->loadLaravelMigrations();
        } else {
            // For MongoDB, manually clear collections and recreate indexes
            $this->artisan('db:wipe', ['--database' => 'mongodb']);
            
            // Recreate unique indexes after wiping
            $connection = $this->app->make('db')->connection('mongodb');
            $connection->getCollection('workflow_logs')->createIndex(
                ['stored_workflow_id' => 1, 'index' => 1],
                ['unique' => true]
            );
        }
    }

    protected function getPackageProviders($app)
    {
        $providers = [\Workflow\Providers\WorkflowServiceProvider::class];

        if (env('DB_CONNECTION') === 'mongodb' && class_exists(\MongoDB\Laravel\MongoDBServiceProvider::class)) {
            $providers[] = \MongoDB\Laravel\MongoDBServiceProvider::class;
        }

        return $providers;
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.connections.mongodb', [
            'driver' => 'mongodb',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 27017),
            'database' => env('DB_DATABASE', 'testbench'),
            'username' => env('DB_USERNAME', ''),
            'password' => env('DB_PASSWORD', ''),
            'options' => [
                'database' => env('DB_AUTHENTICATION_DATABASE', 'admin'),
            ],
        ]);

        if (env('DB_CONNECTION') === 'mongodb') {
            $app['config']->set('workflows.base_model', 'MongoDB\\Laravel\\Eloquent\\Model');
        }
    }
}
