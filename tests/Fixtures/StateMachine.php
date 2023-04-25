<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Exception;

class StateMachine
{
    private $states = [];

    private $transitions = [];

    private $currentState;

    public function addState($state)
    {
        $this->states[] = $state;
    }

    public function addTransition($action, $fromState, $toState)
    {
        $this->transitions[$action] = [
            'from' => $fromState,
            'to' => $toState,
        ];
    }

    public function initialize()
    {
        if (count($this->states) > 0) {
            $this->currentState = $this->states[0];
        }
    }

    public function getCurrentState()
    {
        return $this->currentState;
    }

    public function apply($action)
    {
        if (isset($this->transitions[$action]) && $this->transitions[$action]['from'] === $this->currentState) {
            $this->currentState = $this->transitions[$action]['to'];
        } else {
            throw new Exception('Transition not found,');
        }
    }
}
