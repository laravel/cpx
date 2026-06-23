<?php

namespace Cpx;

class PackageMetadata
{
    public function __construct(
        public Package $package,
        public ?string $lastUpdatedAt = null,
        public ?string $lastRunAt = null,
    ) {}
}
