<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Metadata;

class ListCommand extends Command
{
    public function __invoke(): void
    {
        $metadata = Metadata::open();

        if (empty($metadata->packages)) {
            $this->line('There are no installed packages.');

            return;
        }

        $this->line('Installed Packages:');

        foreach ($metadata->packages as $packageKey => $packageMetadata) {
            $this->line(Command::COLOR_GREEN."  {$packageMetadata->package->fullPackageString()}".Command::COLOR_RESET.' (Last Run: '.($packageMetadata->lastRunAt ?? 'N/A').')');
        }
    }
}
