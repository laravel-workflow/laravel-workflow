<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (getenv('GITHUB_ACTIONS') !== 'true') {
    $subscriber = new Tests\TestSuiteSubscriber();
    PHPUnit\Event\Facade::instance()->registerSubscribers($subscriber);
}
