<?php

declare(strict_types=1);

// Disable output buffering
while (ob_get_level()) {
    ob_end_clean();
}
ob_implicit_flush(true);

echo "[BOOTSTRAP] Starting tests bootstrap\n";
flush();

require_once __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Event\Facade;
use Tests\TestSuiteSubscriber;

echo "[BOOTSTRAP] Registering test subscriber\n";
flush();

$subscriber = new TestSuiteSubscriber();
Facade::instance()->registerSubscribers($subscriber);

echo "[BOOTSTRAP] Bootstrap complete\n";
flush();
