<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Composer;

class UpgradeCommand extends Command
{
    public function __invoke(): void
    {
        $this->line('Updating '.Command::COLOR_GREEN.'cpx');
        Composer::runCommand('global update cpx/cpx');
    }
}
