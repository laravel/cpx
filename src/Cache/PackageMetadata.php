<?php

declare(strict_types=1);

namespace Cpx\Cache;

use Cpx\Packages\Package;

class PackageMetadata
{
    public function __construct(
        public readonly Package $package,
        public ?string $lastUpdatedAt = null,
        public ?string $lastRunAt = null,
    ) {}
}
