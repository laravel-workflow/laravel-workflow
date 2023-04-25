<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Models\StoredWorkflow;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestStateMachineWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    private $stateMachine;

    public function __construct(
        public StoredWorkflow $storedWorkflow,
        ...$arguments
    ) {
        parent::__construct($storedWorkflow, $arguments);

        $this->stateMachine = new StateMachine();

        $this->stateMachine->addState('created');
        $this->stateMachine->addState('submitted');
        $this->stateMachine->addState('approved');
        $this->stateMachine->addState('denied');

        $this->stateMachine->addTransition('submit', 'created', 'submitted');
        $this->stateMachine->addTransition('approve', 'submitted', 'approved');
        $this->stateMachine->addTransition('deny', 'submitted', 'denied');

        $this->stateMachine->initialize();
    }

    #[SignalMethod]
    public function submit()
    {
        $this->stateMachine->apply('submit');
    }

    #[SignalMethod]
    public function approve()
    {
        $this->stateMachine->apply('approve');
    }

    #[SignalMethod]
    public function deny()
    {
        $this->stateMachine->apply('deny');
    }

    public function isSubmitted()
    {
        return $this->stateMachine->getCurrentState() === 'submitted';
    }

    public function isApproved()
    {
        return $this->stateMachine->getCurrentState() === 'approved';
    }

    public function isDenied()
    {
        return $this->stateMachine->getCurrentState() === 'denied';
    }

    public function execute()
    {
        yield WorkflowStub::await(fn () => $this->isSubmitted());

        yield WorkflowStub::await(fn () => $this->isApproved() || $this->isDenied());

        return $this->stateMachine->getCurrentState();
    }
}
