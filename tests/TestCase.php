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
        Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

        for ($i = 0; $i < self::NUMBER_OF_WORKERS; $i++) {
            self::$workers[$i] = new Process(['php', 'artisan', 'queue:work']);
            self::$workers[$i]->start();
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$workers as $worker) {
            $worker->stop();
        }
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
