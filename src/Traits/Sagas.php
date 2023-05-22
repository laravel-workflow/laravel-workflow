<?php

declare(strict_types=1);

namespace Workflow\Traits;

use Throwable;
use Workflow\ActivityStub;

trait Sagas
{
    private array $compensations = [];

    private bool $parallelCompensation = false;

    private bool $continueWithError = false;

    public function setParallelCompensation(bool $parallelCompensation): self
    {
        $this->parallelCompensation = $parallelCompensation;

        return $this;
    }

    public function setContinueWithError(bool $continueWithError): self
    {
        $this->continueWithError = $continueWithError;

        return $this;
    }

    public function addCompensation(callable $compensation): self
    {
        $this->compensations[] = $compensation;

        return $this;
    }

    public function compensate()
    {
        if ($this->parallelCompensation) {
            $compensations = [];
            for (end($this->compensations); key($this->compensations) !== null; prev($this->compensations)) {
                $compensations[] = current($this->compensations);
            }
            yield ActivityStub::all($compensations);
        } else {
            foreach ($this->compensations as $compensation) {
                try {
                    yield $compensation();
                } catch (Throwable $th) {
                    if (! $this->continueWithError) {
                        throw $th;
                    }
                }
            }
        }
    }
}
