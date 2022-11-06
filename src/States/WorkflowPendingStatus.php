<?php

declare(strict_types=1);

namespace Workflow\States;

final class WorkflowPendingStatus extends WorkflowStatus
{
    public static string $name = 'pending';
}
