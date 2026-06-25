<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'version',
    description: 'Show cpx and PHP versions',
)]
class VersionCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
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

        $output->writeln("cpx version: <info>{$cpxVersion}</info>");
        $output->writeln('php version: <info>'.PHP_VERSION.'</info>');

        return self::SUCCESS;
    }
}
