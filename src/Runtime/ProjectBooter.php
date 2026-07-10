<?php

declare(strict_types=1);

namespace Cpx\Runtime;

interface ProjectBooter
{
    public function supports(Context $context): bool;

    /**
     * Boot the project and return the variables to expose to user code.
     *
     * @return array<string, object>
     */
    public function boot(Context $context): array;
}
