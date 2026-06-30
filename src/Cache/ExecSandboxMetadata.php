<?php

declare(strict_types=1);

namespace Cpx\Cache;

class ExecSandboxMetadata
{
    /**
     * @param  list<string>  $packages
     */
    public function __construct(
        public readonly string $key,
        public array $packages = [],
        public ?int $lastUpdatedAt = null,
        public ?int $lastRunAt = null,
    ) {}
}
