<?php

declare(strict_types=1);

namespace Cpx;

use Cpx\Commands\AliasCommand;
use Cpx\Commands\AliasesCommand;
use Cpx\Commands\CleanCommand;
use Cpx\Commands\ExecCommand;
use Cpx\Commands\ListCommand;
use Cpx\Commands\RunPackageCommand;
use Cpx\Commands\TinkerCommand;
use Cpx\Commands\UnaliasCommand;
use Cpx\Commands\UpdateCommand;
use Cpx\Commands\UpgradeCommand;
use Cpx\Packages\PackageCommandRunner;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends SymfonyApplication
{
    public function __construct(?PackageCommandRunner $packageCommandRunner = null)
    {
        parent::__construct('cpx', $this->resolveVersion());
        $this->setAutoExit(false);

        $this->registerCommands($packageCommandRunner ?? new PackageCommandRunner);
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $input ??= new ArgvInput;

        if ($input instanceof ArgvInput && $this->shouldRunPackageFallback($input)) {
            $input = new ArgvInput(['cpx', RunPackageCommand::NAME, '--', ...$input->getRawTokens()]);
        }

        return parent::run($input, $output);
    }

    protected function getCommandName(InputInterface $input): ?string
    {
        $command = parent::getCommandName($input);

        return $command === null || $this->has($command)
            ? $command
            : RunPackageCommand::NAME;
    }

    private function registerCommands(PackageCommandRunner $packageCommandRunner): void
    {
        $this->addCommands([
            new ListCommand,
            new AliasCommand,
            new AliasesCommand,
            new UnaliasCommand,
            new CleanCommand,
            new UpdateCommand,
            new UpgradeCommand,
            new ExecCommand,
            new TinkerCommand,
            new RunPackageCommand($packageCommandRunner),
        ]);
    }

    private function resolveVersion(): string
    {
        $contents = file_get_contents(__DIR__.'/../composer.json');

        if ($contents === false) {
            return 'unknown';
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) && is_string($decoded['version'] ?? null)
            ? $decoded['version']
            : 'unknown';
    }

    private function shouldRunPackageFallback(ArgvInput $input): bool
    {
        $command = $input->getRawTokens()[0] ?? null;

        return is_string($command)
            && ! in_array($command, ['--version', '-v'], true)
            && ! $this->has($command);
    }
}
