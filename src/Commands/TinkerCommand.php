<?php

namespace Cpx\Commands;

use Cpx\Console;
use Cpx\Package;

class TinkerCommand extends Command
{
    public function __invoke(): void
    {
        $psyshConfig = realpath(__DIR__.'/../../files/psysh-config.php');

        if ($psyshConfig === false) {
            $this->error('Unable to find the PsySH configuration file.');

            return;
        }

        Package::parse('psy/psysh')->runCommand(Console::parse("psysh --config {$psyshConfig}"));
    }
}
