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
        echo "[DEBUG] setUpBeforeClass started\n";

        if (getenv('GITHUB_ACTIONS') !== 'true') {
            if (TestSuiteSubscriber::getCurrentSuite() === 'feature') {
                Dotenv::createImmutable(__DIR__, '.env.feature')->safeLoad();
            } elseif (TestSuiteSubscriber::getCurrentSuite() === 'unit') {
                Dotenv::createImmutable(__DIR__, '.env.unit')->safeLoad();
            }
        }

        echo "[DEBUG] Starting queue workers\n";

        // Prepare environment variables for workers (filter out non-scalar values)
        $env = array_filter(array_merge($_SERVER, $_ENV), static fn ($v) => is_string($v) || is_numeric($v));

        for ($i = 0; $i < self::NUMBER_OF_WORKERS; $i++) {
            echo "[DEBUG] Starting worker {$i}\n";
            flush();
            self::$workers[$i] = new Process(
                [
                    'php',
                    __DIR__ . '/../vendor/bin/testbench',
                    'queue:work',
                    '--tries=3',
                    '--timeout=60',
                    '--max-time=300',
                    '-vvv',
                ],
                null,
                $env,
                null,
                300 // Timeout after 5 minutes
            );

            // Redirect worker output to stdout for debugging
            self::$workers[$i]->start(static function ($type, $buffer) use ($i) {
                echo "[WORKER-{$i}] {$buffer}";
                flush();
            });

            echo "[DEBUG] Worker {$i} started\n";
            flush();

            // In GitHub Actions, add a small delay and check if worker started
            if (getenv('GITHUB_ACTIONS') === 'true') {
                usleep(500000); // 0.5 second delay
                if (! self::$workers[$i]->isRunning()) {
                    echo "Warning: Worker {$i} failed to start or exited immediately\n";
                    echo 'Output: ' . self::$workers[$i]->getOutput() . "\n";
                    echo 'Error: ' . self::$workers[$i]->getErrorOutput() . "\n";
                } else {
                    echo "[DEBUG] Worker {$i} is running\n";
                }
            }
        }

        echo "[DEBUG] setUpBeforeClass finished\n";
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$workers as $worker) {
            $worker->stop();
        }
    }

    protected function setUp(): void
    {
        echo "[DEBUG] TestCase::setUp() ENTERED\n";
        flush();

        if (getenv('GITHUB_ACTIONS') !== 'true') {
            if (TestSuiteSubscriber::getCurrentSuite() === 'feature') {
                Dotenv::createImmutable(__DIR__, '.env.feature')->safeLoad();
            } elseif (TestSuiteSubscriber::getCurrentSuite() === 'unit') {
                Dotenv::createImmutable(__DIR__, '.env.unit')->safeLoad();
            }
        }

        echo "[DEBUG] TestCase::setUp() calling parent::setUp()\n";
        flush();

        parent::setUp();

        echo "[DEBUG] TestCase::setUp() FINISHED\n";
        flush();
    }

    protected function defineDatabaseMigrations()
    {
        echo "[DEBUG] defineDatabaseMigrations() ENTERED\n";
        flush();

        if (env('DB_CONNECTION') !== 'mongodb') {
            echo "[DEBUG] Using non-MongoDB connection\n";
            flush();

            $this->artisan('migrate:fresh', [
                '--path' => dirname(__DIR__) . '/src/migrations',
                '--realpath' => true,
            ]);

            $this->loadLaravelMigrations();
        } else {
            echo "[DEBUG] Using MongoDB connection\n";
            flush();

            echo "[DEBUG] Running db:wipe for MongoDB...\n";
            flush();

            $this->artisan('db:wipe', [
                '--database' => 'mongodb',
            ]);

            echo "[DEBUG] db:wipe completed\n";
            flush();

            echo "[DEBUG] Creating MongoDB indexes...\n";
            flush();

            // Create unique indexes for MongoDB
            $this->createMongoDBIndexes();

            echo "[DEBUG] MongoDB indexes created\n";
            flush();
        }

        echo "[DEBUG] defineDatabaseMigrations() FINISHED\n";
        flush();
    }

    /**
     * Create required indexes for MongoDB.
     */
    protected function createMongoDBIndexes(): void
    {
        echo "[DEBUG] createMongoDBIndexes() ENTERED\n";
        flush();

        echo "[DEBUG] Getting MongoDB connection\n";
        flush();

        $db = app('db')
            ->connection('mongodb');

        echo "[DEBUG] MongoDB connection obtained\n";
        flush();

        // workflow_logs: unique index on stored_workflow_id + index
        echo "[DEBUG] Creating workflow_logs index\n";
        flush();

        $db->getCollection('workflow_logs')
            ->createIndex([
                'stored_workflow_id' => 1,
                'index' => 1,
            ], [
                'unique' => true,
            ]);

        echo "[DEBUG] workflow_logs index created\n";
        flush();

        // workflow_signals: unique index on stored_workflow_id + index (partial: only when index exists and is not null)
        echo "[DEBUG] Creating workflow_signals index\n";
        flush();

        $db->getCollection('workflow_signals')
            ->createIndex(
                [
                    'stored_workflow_id' => 1,
                    'index' => 1,
                ],
                [
                    'unique' => true,
                    'partialFilterExpression' => [
                        'index' => [
                            '$type' => 'number',
                        ],
                    ],
                ]
            );

        echo "[DEBUG] workflow_signals index created\n";
        flush();

        // workflow_timers: unique index on stored_workflow_id + index
        echo "[DEBUG] Creating workflow_timers index\n";
        flush();

        $db->getCollection('workflow_timers')
            ->createIndex([
                'stored_workflow_id' => 1,
                'index' => 1,
            ], [
                'unique' => true,
            ]);

        echo "[DEBUG] workflow_timers index created\n";
        flush();

        // workflow_exceptions: unique index on stored_workflow_id + index
        echo "[DEBUG] Creating workflow_exceptions index\n";
        flush();

        $db->getCollection('workflow_exceptions')
            ->createIndex([
                'stored_workflow_id' => 1,
                'index' => 1,
            ], [
                'unique' => true,
            ]);

        echo "[DEBUG] workflow_exceptions index created\n";
        flush();

        echo "[DEBUG] createMongoDBIndexes() FINISHED\n";
        flush();
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
        if (env('DB_CONNECTION') === 'mongodb') {
            $app['config']->set('workflows.base_model', 'MongoDB\\Laravel\\Eloquent\\Model');

            // Configure MongoDB database connection
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

            $app['config']->set('database.default', 'mongodb');
        }
    }
}
