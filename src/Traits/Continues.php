<?php

declare(strict_types=1);

namespace Workflow\Traits;

use Workflow\States\WorkflowContinuedStatus;

trait Continues
{
    public static function continue(...$arguments)
    {
        self::make(self::$context->storedWorkflow->class, self::$context->storedWorkflow->uuid)
            ->start(...$arguments);

        return WorkflowContinuedStatus::class;
    }
}
