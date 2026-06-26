<?php

declare(strict_types=1);

namespace Cpx\Packages;

readonly class PackageAlias
{
    public function __construct(
        public string $name,
        public string $description,
        public string $command,
        public string $package,
    ) {}
}
