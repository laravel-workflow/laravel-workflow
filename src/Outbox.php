<?php

declare(strict_types=1);

namespace Workflow;

final class Outbox
{
    public array $values = [];

    public int $transmitted = 0;

    public int $sent = 0;

    public function send(mixed $value): void
    {
        $this->values[] = $value;
        $this->transmitted++;
    }

    public function hasUnsent(): bool
    {
        return $this->transmitted > $this->sent;
    }

    public function nextUnsent(): mixed
    {
        if (! $this->hasUnsent()) {
            return null;
        }

        $value = $this->values[$this->sent];
        $this->sent++;

        return $value;
    }
}
