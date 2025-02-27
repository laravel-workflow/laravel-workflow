<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Event\TestSuite\Started;
use PHPUnit\Event\TestSuite\StartedSubscriber;

class TestSuiteSubscriber implements StartedSubscriber
{
    private static string $currentSuite = '';

    public function notify(Started $event): void
    {
        $suiteName = $event->testSuite()
            ->name();

        if (in_array($suiteName, ['unit', 'feature'], true)) {
            self::$currentSuite = $suiteName;
        }
    }

    public static function getCurrentSuite(): string
    {
        return self::$currentSuite;
    }
}
