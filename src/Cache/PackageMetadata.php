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
    ) {}

    public function installPath(): string
    {
        return $this->package->installPath();
    }

    public function lastRunForDisplay(): string
    {
        return $this->lastRunAt === null ? 'N/A' : date('Y-m-d H:i:s', $this->lastRunAt);
    }
}
