<?php

declare(strict_types=1);

namespace Tests;

use Dotenv\Dotenv;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Symfony\Component\Process\Process;

abstract class TestCase extends BaseTestCase
{
    private static \Symfony\Component\Process\Process $process;

    public static function setUpBeforeClass(): void
    {
        Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
        self::$process = new Process([
            'php',
            '-dxdebug.mode=debug',
            '-dxdebug.client_host=127.0.0.1',
            '-dxdebug.client_port=9003',
            '-dxdebug.start_with_request=trigger',
            'artisan',
            'queue:work'
        ]);
        self::$process->start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$process->stop();
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadLaravelMigrations();

        $this->artisan('migrate:fresh', [
            '--path' => dirname(__DIR__) . '/src/migrations',
            '--realpath' => true,
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [\Workflow\Providers\WorkflowServiceProvider::class];
    }
}
