<?php

declare(strict_types=1);

namespace Workflow\Traits;

use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use Workflow\ContinuedWorkflow;
use Workflow\Models\StoredWorkflow;

trait Continues
{
    public static function continueAsNew(...$arguments): PromiseInterface
    {
        $context = self::$context;

        if (! $context->replaying) {
            $newWorkflow = self::make($context->storedWorkflow->class);
            $newWorkflow->start(...$arguments);

            $newWorkflow->storedWorkflow->parents()
                ->attach($context->storedWorkflow, [
                    'parent_index' => StoredWorkflow::CONTINUE_PARENT_INDEX,
                    'parent_now' => $context->now,
                ]);

            $rootWorkflow = $context->storedWorkflow->parents()
                ->wherePivot('parent_index', StoredWorkflow::ACTIVE_WORKFLOW_INDEX)->first();

            if ($rootWorkflow) {
                $rootWorkflow->children()
                    ->wherePivot('parent_index', StoredWorkflow::ACTIVE_WORKFLOW_INDEX)->detach();

                $rootWorkflow->children()
                    ->attach($newWorkflow->storedWorkflow, [
                        'parent_index' => StoredWorkflow::ACTIVE_WORKFLOW_INDEX,
                        'parent_now' => $context->now,
                    ]);
            }
        }

        self::$context = $context;

        return resolve(new ContinuedWorkflow());
    }
}
