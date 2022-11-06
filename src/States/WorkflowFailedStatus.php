<?php

declare(strict_types=1);

namespace Workflow\States;

final class WorkflowFailedStatus extends WorkflowStatus
{
    public static string $name = 'failed';
}
