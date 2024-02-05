<?php

declare(strict_types=1);

namespace Tests;


abstract class TestCaseRequiringDatabase extends TestCase
{

    protected function defineDatabaseMigrations()
    {
        $this->artisan('migrate:fresh', [
            '--path' => dirname(__DIR__) . '/src/migrations',
            '--realpath' => true,
        ]);
        $this->artisan('schema:dump');

        $this->loadLaravelMigrations();
    }
}
