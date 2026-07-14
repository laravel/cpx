<?php

namespace Cpx\Commands;

use Cpx\Package;
use Cpx\Metadata;

class CleanCommand extends Command
{
    public function __invoke()
    {
        $days = (int) ($this->console->getOption('days') ?? 30);

        $metadata = Metadata::open();
        $timeLimit = time() - ($days * 24 * 3600);
        $cleanedSomething = false;

        foreach ($metadata->packages as $packageKey => $packageMetadata) {
            $lastRun = strtotime($packageMetadata->lastRunAt ?? '1970-01-01 00:00:00');

            if ($this->console->hasOption('all') || $lastRun < $timeLimit) {
                $package = Package::parse($packageKey);
                $this->line(Command::COLOR_GREEN . "Removing unused package {$package}...");
                $package->delete();
                unset($metadata->packages[$packageKey]);
                $cleanedSomething = true;
            }
        }

        foreach ($metadata->execCache as $sandboxDir => $cacheEntry) {
            $lastRun = is_array($cacheEntry)
                ? (int) ($cacheEntry['last_run'] ?? 0)
                : strtotime($cacheEntry->lastRunAt ?? '1970-01-01 00:00:00');

            if ($this->console->hasOption('all') || $lastRun < $timeLimit) {
                $packageDirectory = cpx_path(".exec_cache/{$sandboxDir}");
                exec("rm -rf {$packageDirectory}");
                $this->line(Command::COLOR_GREEN . "Removing exec sandbox cache {$sandboxDir}...");
                unset($metadata->execCache[$sandboxDir]);
                $cleanedSomething = true;
            }
        }

        $metadata->save();

        if (!$cleanedSomething) {
            $this->success("There were no packages to clean.");
        }
    }
}
