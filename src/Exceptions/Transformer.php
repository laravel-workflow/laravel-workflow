<?php

declare(strict_types=1);

namespace Workflow\Exceptions;

use LimitIterator;
use SplFileObject;
use Throwable;
use Workflow\Serializers\Y;

class Transformer
{
    protected int $traceDepth = PHP_INT_MAX;
    protected bool $traceArgs = true;

    public function withDepth(int $depth): self
    {
        $this->traceDepth = $depth;
        return $this;
    }

    public function withoutArgs(): self
    {
        $this->traceArgs = false;
        return $this;
    }

    /**
     * @param Throwable $throwable
     * @return array{class: string, message: string, code: int|string, line: int, file: string, trace: mixed[], snippet: string[]}
     */
    public function transform(Throwable $throwable): array
    {
        $file = new SplFileObject($throwable->getFile());
        $iterator = new LimitIterator($file, max(0, $throwable->getLine() - 4), 7);

        $trace = array_slice($throwable->getTrace(), 0, $this->traceDepth);
        if (!$this->traceArgs) {
            foreach ($trace as &$entry) {
                $entry['args'] = [];
            }
            unset($entry);
        }

        $data = [
            'class' => get_class($throwable),
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'line' => $throwable->getLine(),
            'file' => $throwable->getFile(),
            'trace' => collect($trace)
                ->filter(static fn ($trace) => Y::serializable($trace))
                ->toArray(),
            'snippet' => array_slice(iterator_to_array($iterator), 0, 7),
        ];

        return $data;
    }
}
