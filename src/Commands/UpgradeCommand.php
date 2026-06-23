<?php

namespace Cpx\Commands;

use Cpx\Composer;

class UpgradeCommand extends Command
{
    public function __invoke()
    {
        $this->line('Updating '.Command::COLOR_GREEN.'cpx');
        Composer::runCommand('global update cpx/cpx');
    }
}
