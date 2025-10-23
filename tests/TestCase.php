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
        file_put_contents('php://stderr', "[DEBUG] setUpBeforeClass started\n");
        echo "[DEBUG] setUpBeforeClass started\n";
        flush();

        if (getenv('GITHUB_ACTIONS') !== 'true') {
            if (TestSuiteSubscriber::getCurrentSuite() === 'feature') {
                Dotenv::createImmutable(__DIR__, '.env.feature')->safeLoad();
            } elseif (TestSuiteSubscriber::getCurrentSuite() === 'unit') {
                Dotenv::createImmutable(__DIR__, '.env.unit')->safeLoad();
            }
        }

        file_put_contents('php://stderr', "[DEBUG] Starting queue workers\n");
        echo "[DEBUG] Starting queue workers\n";
        flush();

        // Test Redis connection first
        if (getenv('GITHUB_ACTIONS') === 'true') {
            try {
                $redis = new \Redis();
                $redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int) (getenv('REDIS_PORT') ?: 6379));
                file_put_contents('php://stderr', "[DEBUG] Redis connection test SUCCESSFUL\n");
                $redis->close();
            } catch (\Exception $e) {
                file_put_contents('php://stderr', '[DEBUG] Redis connection test FAILED: ' . $e->getMessage() . "\n");
            }
        }

        // Prepare environment variables for workers (filter out non-scalar values)
        $env = array_filter(array_merge($_SERVER, $_ENV), static fn ($v) => is_string($v) || is_numeric($v));

        // Explicitly add GitHub Actions env vars for workers
        if (getenv('GITHUB_ACTIONS') === 'true') {
            $env['GITHUB_ACTIONS'] = 'true';
            file_put_contents(
                'php://stderr',
                '[DEBUG] Worker env DB_CONNECTION: ' . ($env['DB_CONNECTION'] ?? 'NOT SET') . "\n"
            );
            file_put_contents('php://stderr', '[DEBUG] Worker env DB_HOST: ' . ($env['DB_HOST'] ?? 'NOT SET') . "\n");
            file_put_contents('php://stderr', '[DEBUG] Worker env DB_PORT: ' . ($env['DB_PORT'] ?? 'NOT SET') . "\n");
            file_put_contents(
                'php://stderr',
                '[DEBUG] Worker env DB_DATABASE: ' . ($env['DB_DATABASE'] ?? 'NOT SET') . "\n"
            );
            file_put_contents(
                'php://stderr',
                '[DEBUG] Worker env DB_USERNAME: ' . ($env['DB_USERNAME'] ?? 'NOT SET') . "\n"
            );
            file_put_contents(
                'php://stderr',
                '[DEBUG] Worker env DB_AUTHENTICATION_DATABASE: ' . ($env['DB_AUTHENTICATION_DATABASE'] ?? 'NOT SET') . "\n"
            );
            file_put_contents(
                'php://stderr',
                '[DEBUG] Worker env QUEUE_CONNECTION: ' . ($env['QUEUE_CONNECTION'] ?? 'NOT SET') . "\n"
            );
            file_put_contents(
                'php://stderr',
                '[DEBUG] Worker env REDIS_HOST: ' . ($env['REDIS_HOST'] ?? 'NOT SET') . "\n"
            );
            file_put_contents(
                'php://stderr',
                '[DEBUG] Worker env REDIS_PORT: ' . ($env['REDIS_PORT'] ?? 'NOT SET') . "\n"
            );
        }

        file_put_contents('php://stderr', '[DEBUG] About to start ' . self::NUMBER_OF_WORKERS . " workers\n");

        for ($i = 0; $i < self::NUMBER_OF_WORKERS; $i++) {
            file_put_contents('php://stderr', "[DEBUG] Starting worker {$i}\n");
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
                    '--once',
                    '-vvv',
                ],
                null,
                $env,
                null,
                300 // Timeout after 5 minutes
            );

            // Redirect worker output to stdout for debugging
            self::$workers[$i]->start(static function ($type, $buffer) use ($i) {
                file_put_contents('php://stderr', "[WORKER-{$i}] {$buffer}");
                echo "[WORKER-{$i}] {$buffer}";
                flush();
            });

            file_put_contents('php://stderr', "[DEBUG] Worker {$i} started\n");
            echo "[DEBUG] Worker {$i} started\n";
            flush();

            // In GitHub Actions, add a small delay and check if worker started
            if (getenv('GITHUB_ACTIONS') === 'true') {
                usleep(500000); // 0.5 second delay
                if (! self::$workers[$i]->isRunning()) {
                    $msg = "Warning: Worker {$i} failed to start or exited immediately\n";
                    $msg .= 'Output: ' . self::$workers[$i]->getOutput() . "\n";
                    $msg .= 'Error: ' . self::$workers[$i]->getErrorOutput() . "\n";
                    file_put_contents('php://stderr', $msg);
                    echo $msg;
                } else {
                    file_put_contents('php://stderr', "[DEBUG] Worker {$i} is running\n");
                    echo "[DEBUG] Worker {$i} is running\n";
                }
            }
        }

        file_put_contents('php://stderr', "[DEBUG] setUpBeforeClass finished\n");
        echo "[DEBUG] setUpBeforeClass finished\n";
        flush();
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$workers as $worker) {
            $worker->stop();
        }
    }

    protected function setUp(): void
    {
        file_put_contents('php://stderr', "[DEBUG] TestCase::setUp() ENTERED\n");
        echo "[DEBUG] TestCase::setUp() ENTERED\n";
        flush();

        if (getenv('GITHUB_ACTIONS') !== 'true') {
            if (TestSuiteSubscriber::getCurrentSuite() === 'feature') {
                Dotenv::createImmutable(__DIR__, '.env.feature')->safeLoad();
            } elseif (TestSuiteSubscriber::getCurrentSuite() === 'unit') {
                Dotenv::createImmutable(__DIR__, '.env.unit')->safeLoad();
            }
        }

        file_put_contents('php://stderr', "[DEBUG] TestCase::setUp() calling parent::setUp()\n");
        echo "[DEBUG] TestCase::setUp() calling parent::setUp()\n";
        flush();

        parent::setUp();

        file_put_contents('php://stderr', "[DEBUG] TestCase::setUp() FINISHED\n");
        echo "[DEBUG] TestCase::setUp() FINISHED\n";
        flush();
    }

    protected function defineDatabaseMigrations()
    {
        file_put_contents('php://stderr', "[DEBUG] defineDatabaseMigrations() ENTERED\n");
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
            file_put_contents('php://stderr', "[DEBUG] Using MongoDB connection\n");
            echo "[DEBUG] Using MongoDB connection\n";
            flush();

            file_put_contents('php://stderr', "[DEBUG] Running db:wipe for MongoDB...\n");
            echo "[DEBUG] Running db:wipe for MongoDB...\n";
            flush();

            $this->artisan('db:wipe', [
                '--database' => 'mongodb',
            ]);

            file_put_contents('php://stderr', "[DEBUG] db:wipe completed\n");
            echo "[DEBUG] db:wipe completed\n";
            flush();

            file_put_contents('php://stderr', "[DEBUG] Flushing Redis queue...\n");
            echo "[DEBUG] Flushing Redis queue...\n";
            flush();

            // Flush Redis to clear any pending jobs
            $this->artisan('queue:flush');

            file_put_contents('php://stderr', "[DEBUG] Redis queue flushed\n");
            echo "[DEBUG] Redis queue flushed\n";
            flush();

            file_put_contents('php://stderr', "[DEBUG] Clearing Laravel logs...\n");
            echo "[DEBUG] Clearing Laravel logs...\n";
            flush();

            // Clear Laravel logs
            $logPath = dirname(__DIR__) . '/vendor/orchestra/testbench-core/laravel/storage/logs/laravel.log';
            if (file_exists($logPath)) {
                file_put_contents($logPath, '');
            }

            file_put_contents('php://stderr', "[DEBUG] Laravel logs cleared\n");
            echo "[DEBUG] Laravel logs cleared\n";
            flush();

            file_put_contents('php://stderr', "[DEBUG] Creating MongoDB indexes...\n");
            echo "[DEBUG] Creating MongoDB indexes...\n";
            flush();

            // Create unique indexes for MongoDB
            $this->createMongoDBIndexes();

            file_put_contents('php://stderr', "[DEBUG] MongoDB indexes created\n");
            echo "[DEBUG] MongoDB indexes created\n";
            flush();
        }

        file_put_contents('php://stderr', "[DEBUG] defineDatabaseMigrations() FINISHED\n");
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
