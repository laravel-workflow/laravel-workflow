<?php

declare(strict_types=1);

namespace Workflow\States;

final class WorkflowWaitingStatus extends WorkflowStatus
{
    public static string $name = 'waiting';
}
