<?php

declare(strict_types=1);

namespace Cpx\Commands;

class VersionCommand extends Command
{
    public function __invoke()
    {
        $cpxVersion = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true)['version'] ?? 'unknown';
        $this->line('cpx version: '.Command::COLOR_GREEN.$cpxVersion);
        $this->line('php version: '.Command::COLOR_GREEN.PHP_VERSION);
    }
}
