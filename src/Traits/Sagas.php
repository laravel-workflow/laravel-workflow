<?php

declare(strict_types=1);

namespace Workflow\Traits;

use Generator;
use Throwable;
use Workflow\ActivityStub;

trait Sagas
{
    /**
     * @var callable[]
     */
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

    /**
     * @return Generator<int, mixed, mixed, void>
     * @throws Throwable
     */
    public function compensate(): Generator
    {
        if ($this->parallelCompensation) {
            $compensations = [];
            foreach ($this->compensations as $compensation) {
                $compensations[] = $compensation();
            }
            yield ActivityStub::all($compensations);
        } else {
            for (end($this->compensations); key($this->compensations) !== null; prev($this->compensations)) {
                try {
                    yield current($this->compensations)();
                } catch (Throwable $th) {
                    if (! $this->continueWithError) {
                        throw $th;
                    }
                }
            }
        }
    }
}
