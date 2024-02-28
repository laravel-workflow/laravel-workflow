<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Exception;

class StateMachine
{
    /**
     * @var string[]
     */
    private array $states = [];

    /**
     * @var array<string, array{from: string, to: string}>
     */
    private array $transitions = [];

    private string $currentState;

    public function addState(string $state): void
    {
        $this->states[] = $state;
    }

    public function addTransition(string $action, string $fromState, string $toState): void
    {
        $this->transitions[$action] = [
            'from' => $fromState,
            'to' => $toState,
        ];
    }

    public function initialize(): void
    {
        if (count($this->states) > 0) {
            $this->currentState = $this->states[0];
        }
    }

    public function getCurrentState(): string
    {
        return $this->currentState;
    }

    public function apply(string $action): void
    {
        if (isset($this->transitions[$action]) && $this->transitions[$action]['from'] === $this->currentState) {
            $this->currentState = $this->transitions[$action]['to'];
        } else {
            throw new Exception('Transition not found,');
        }
    }
}
