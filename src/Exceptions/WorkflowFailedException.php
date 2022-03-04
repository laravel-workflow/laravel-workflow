<?php

namespace Workflow\Exceptions;

use Exception;

class WorkflowFailedException extends Exception
{
    public function __construct($message = 'Workflow Failed.')
    {
        parent::__construct($message);
    }
}
