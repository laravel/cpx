<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Composer\ComposerRunner;
use Cpx\Composer\ComposerSource;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;

#[AsCommand(
    name: 'upgrade',
    description: 'Upgrade cpx itself',
)]
class UpgradeCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        info('Updating cpx');

        return ComposerRunner::run(['global', 'update', 'cpx/cpx'], source: ComposerSource::Device);
    }
}
