<?php

declare(strict_types=1);

namespace Workflow\Traits;

trait Continues
{
    public static function continueAsNew(...$arguments): void
    {
        self::make(self::$context->storedWorkflow->class, self::$context->storedWorkflow->uuid)
            ->start(...$arguments);
    }
}
