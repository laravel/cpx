<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Input\PackageInvocation;
use Cpx\Packages\Package;
use Cpx\Runtime\Environment;
use Cpx\Support\Filesystem;
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
        $psyshConfig = $this->psyshConfigPath();

        if ($psyshConfig === null) {
            error('Unable to find the PsySH configuration file.');

            return self::FAILURE;
        }

        return Package::parse('psy/psysh')->runCommand(
            PackageInvocation::fromRawTokens(['psysh', '--config', $psyshConfig]),
        );
    }

    private function psyshConfigPath(): ?string
    {
        $pharPath = Environment::pharPath();

        if ($pharPath === '') {
            $bundled = __DIR__.'/../../files/psysh-config.php';

            return file_exists($bundled) ? $bundled : null;
        }

        // The psysh child process cannot read phar:// paths, so hand it a stub that loads the phar first.
        $config = cpx_path('psysh-config.php');
        $stub = sprintf(
            "<?php\n\nPhar::loadPhar('%s', 'cpx.phar');\n\nreturn require 'phar://cpx.phar/files/psysh-config.php';\n",
            addcslashes($pharPath, "\\'"),
        );

        Filesystem::ensureDirectory(cpx_path());
        Filesystem::writeAtomic($config, $stub);

        return $config;
    }
}
