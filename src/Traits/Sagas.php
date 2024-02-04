<?php

declare(strict_types=1);

namespace Workflow\Traits;

use Generator;
use Closure;
use React\Promise\PromiseInterface;
use Throwable;
use Workflow\ActivityStub;
use function PHPStan\dumpType;

trait Sagas
{
    /**
     * @var Closure[]
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

    /**
     * @param Closure $compensation
     */
    public function addCompensation(Closure $compensation): self
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
                if (false === ($currentCompensation = current($this->compensations))) {
                    continue;
                }
                try {
                    yield $currentCompensation();
                } catch (Throwable $th) {
                    if (! $this->continueWithError) {
                        throw $th;
                    }
                }
            }
        }
    }
}
