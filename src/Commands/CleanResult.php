<?php

declare(strict_types=1);

namespace Cpx\Commands;

class CleanResult
{
    /** @var list<string> */
    public array $removed = [];

    /** @var list<string> */
    public array $failures = [];

    public function recordRemoval(string $description): void
    {
        $this->removed[] = $description;
    }

    public function recordFailure(string $message): void
    {
        $this->failures[] = $message;
    }

    public function isEmpty(): bool
    {
        return $this->removed === [] && $this->failures === [];
    }

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }
}
