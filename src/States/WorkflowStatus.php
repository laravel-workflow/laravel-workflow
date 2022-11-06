<?php

declare(strict_types=1);

namespace Workflow\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class WorkflowStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(WorkflowCreatedStatus::class)
            ->allowTransition(WorkflowCreatedStatus::class, WorkflowPendingStatus::class)
            ->allowTransition(WorkflowFailedStatus::class, WorkflowPendingStatus::class)
            ->allowTransition(WorkflowPendingStatus::class, WorkflowFailedStatus::class)
            ->allowTransition(WorkflowPendingStatus::class, WorkflowRunningStatus::class)
            ->allowTransition(WorkflowRunningStatus::class, WorkflowCompletedStatus::class)
            ->allowTransition(WorkflowRunningStatus::class, WorkflowFailedStatus::class)
            ->allowTransition(WorkflowRunningStatus::class, WorkflowWaitingStatus::class)
            ->allowTransition(WorkflowWaitingStatus::class, WorkflowFailedStatus::class)
            ->allowTransition(WorkflowWaitingStatus::class, WorkflowPendingStatus::class)
        ;
    }
}
