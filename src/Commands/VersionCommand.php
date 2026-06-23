<?php

declare(strict_types=1);

namespace Cpx\Commands;

class VersionCommand extends Command
{
    public function __invoke(): void
    {
        $contents = file_get_contents(__DIR__.'/../../composer.json');
        $composerData = [];

        if ($contents !== false) {
            $decoded = json_decode($contents, true);

            if (is_array($decoded)) {
                $composerData = $decoded;
            }
        }

        $cpxVersion = is_string($composerData['version'] ?? null) ? $composerData['version'] : 'unknown';

        $this->line('cpx version: '.Command::COLOR_GREEN.$cpxVersion);
        $this->line('php version: '.Command::COLOR_GREEN.PHP_VERSION);
    }
}
