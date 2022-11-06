<?php

declare(strict_types=1);

namespace Workflow\States;

final class WorkflowCompletedStatus extends WorkflowStatus
{
    public static string $name = 'completed';
}
