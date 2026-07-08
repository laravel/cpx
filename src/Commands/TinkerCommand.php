<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Input\PackageInvocation;
use Cpx\Packages\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;

#[AsCommand(
    name: 'tinker',
    description: 'Open an interactive REPL',
)]
class TinkerCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $psyshConfig = realpath(__DIR__.'/../../files/psysh-config.php');

        if ($psyshConfig === false) {
            error('Unable to find the PsySH configuration file.');

            return self::FAILURE;
        }

        return Package::parse('psy/psysh')->runCommand(
            PackageInvocation::fromRawTokens(['psysh', '--config', $psyshConfig]),
        );
    }
}
