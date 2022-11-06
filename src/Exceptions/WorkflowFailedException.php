<?php

declare(strict_types=1);

namespace Workflow\Exceptions;

use Exception;

final class WorkflowFailedException extends Exception
{
    public function __construct($message = 'Workflow Failed.')
    {
        parent::__construct($message);
    }
}
