<?php

declare(strict_types=1);

namespace Cpx;

class PackageMetadata
{
    public function __construct(
        public Package $package,
        public ?string $lastUpdatedAt = null,
        public ?string $lastRunAt = null,
    ) {}
}
