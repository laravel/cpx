<?php

declare(strict_types=1);

namespace Cpx\Cache;

use Cpx\Packages\Package;

class PackageMetadata
{
    public function __construct(
        public readonly Package $package,
        public ?int $lastUpdatedAt = null,
        public ?int $lastRunAt = null,
    ) {
        //
    }

    public function installPath(): string
    {
        return $this->package->installPath();
    }

    public function lastRun(): ?string
    {
        return $this->lastRunAt === null ? null : date('Y-m-d H:i:s', $this->lastRunAt);
    }
}
