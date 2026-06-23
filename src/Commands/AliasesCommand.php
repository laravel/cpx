<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\PackageAliases;

class AliasesCommand extends Command
{
    public function __invoke(): void
    {
        $this->line('Aliased packages:'.PHP_EOL);
        $packages = PackageAliases::$packages;
        usort($packages, fn ($a, $b) => strcmp($a['command'], $b['command']));

        foreach ($packages as $package) {
            $paddedCommand = str_pad($package['command'], 15);
            $this->line('  '.Command::COLOR_GREEN.'cpx '.$paddedCommand.Command::COLOR_RESET.'   '.$package['description']);
        }
    }
}
