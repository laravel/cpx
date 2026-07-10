<?php

declare(strict_types=1);

namespace Cpx\Runtime;

interface ReplLauncher
{
    /**
     * The command for the project's native REPL, or null when unavailable.
     *
     * @return list<string>|null
     */
    public function replCommand(Context $context): ?array;
}
