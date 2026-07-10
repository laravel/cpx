<?php

declare(strict_types=1);

namespace Cpx\Runtime;

readonly class Context
{
    public function __construct(
        public string $workingDirectory,
        public ?string $autoloadRoot,
        public bool $shouldBoot = true,
        public bool $shouldAliasClasses = true,
        public bool $verbose = false,
    ) {}
}
