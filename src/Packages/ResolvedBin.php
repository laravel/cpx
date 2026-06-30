<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Cpx\Input\PackageInvocation;

readonly class ResolvedBin
{
    public function __construct(
        public string $command,
        public PackageInvocation $invocation,
    ) {
        //
    }
}
