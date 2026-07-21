<?php

declare(strict_types=1);

namespace Cpx\Process;

readonly class ProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
    ) {}
}
