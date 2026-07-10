<?php

declare(strict_types=1);

namespace Cpx\Runtime;

class GenericLoader implements ProjectBooter
{
    public function supports(Context $context): bool
    {
        return true;
    }

    /** @return array<string, object> */
    public function boot(Context $context): array
    {
        return [];
    }
}
