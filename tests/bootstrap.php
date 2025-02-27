<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/classAliases.php';

use PHPUnit\Event\Facade;
use Tests\TestSuiteSubscriber;

$subscriber = new TestSuiteSubscriber();
Facade::instance()->registerSubscribers($subscriber);
