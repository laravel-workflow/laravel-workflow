<?php

namespace Workflow;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use ReflectionClass;
use Workflow\Models\StoredWorkflow;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowFailedStatus;
use Workflow\States\WorkflowPendingStatus;
use function React\Promise\resolve;

class WorkflowStub
{
    protected $model;
    protected $await;

    private function __construct($model)
    {
        $this->model = $model;
    }

    public static function make($class)
    {
        $model = StoredWorkflow::create([
            'class' => $class,
        ]);

        return new static($model);
    }

    public static function load($id)
    {
        return static::fromStoredWorkflow(StoredWorkflow::findOrFail($id));
    }

    public static function fromStoredWorkflow(StoredWorkflow $model)
    {
        return new static($model);
    }

    public static function await($condition): PromiseInterface
    {
        $result = $condition();

        if ($result === true) {
            return resolve(true);
        }

        $deferred = new Deferred();

        return $deferred->promise();
    }

    public function id()
    {
        return $this->model->id;
    }

    public function output()
    {
        return unserialize($this->model->fresh()->output);
    }

    public function running()
    {
        return !in_array($this->status(), [
            WorkflowCompletedStatus::class,
            WorkflowFailedStatus::class,
        ]);
    }

    public function status()
    {
        return get_class($this->model->fresh()->status);
    }

    public function fresh()
    {
        $this->model->refresh();

        return $this;
    }

    public function restart(...$arguments)
    {
        $this->model->arguments = serialize($arguments);
        $this->model->output = null;
        $this->model->logs()->delete();

        $this->dispatch();
    }

    public function resume()
    {
        $this->dispatch();
    }

    public function start(...$arguments)
    {
        $this->model->arguments = serialize($arguments);

        $this->dispatch();
    }

    public function fail($exception)
    {
        $this->model->output = serialize((string) $exception);

        $this->model->status->transitionTo(WorkflowFailedStatus::class);
    }

    public function next($index, $result)
    {
        $this->model->logs()->create([
            'index' => $index,
            'result' => serialize($result),
        ]);

        $this->dispatch();
    }

    private function dispatch()
    {
        $this->model->status->transitionTo(WorkflowPendingStatus::class);

        $this->model->class::dispatch($this->model, ...unserialize($this->model->arguments));
    }

    public function __call($method, $arguments)
    {
        if (collect((new ReflectionClass($this->model->class))->getMethods())
            ->filter(function ($method) {
                return collect($method->getAttributes())
                    ->contains(function ($attribute) {
                        return $attribute->getName() === SignalMethod::class;
                    });
            })
            ->map(function ($method) {
                return $method->getName();
            })->contains($method)
        ) {
            $this->model->signals()->create([
                'method' => $method,
                'arguments' => serialize($arguments),
            ]);

            while ($this->running()) {
                try {
                    $this->fresh()->dispatch();
                    break;
                } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound $th) {
                    usleep(1000);
                }
            }
        }
    }
}
