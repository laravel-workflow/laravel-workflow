<?php

declare(strict_types=1);

namespace Tests;

use Symfony\Component\Process\Process;

abstract class TestCaseRequiringWorkers extends TestCaseRequiringDatabase
{
    public const NUMBER_OF_WORKERS = 2;

    /**
     * @var Process
     */
    private static array $workers = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        for ($i = 0; $i < self::NUMBER_OF_WORKERS; $i++) {
            self::$workers[$i] = new Process(['php', 'artisan', 'queue:work'], null, $_ENV);
            self::$workers[$i]->start();
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$workers as $worker) {
            $worker->stop();
        }
    }
}
