<?php

declare(strict_types=1);

namespace Cpx;

use Cpx\Commands\AliasesCommand;
use Cpx\Commands\CleanCommand;
use Cpx\Commands\ExecCommand;
use Cpx\Commands\ListCommand;
use Cpx\Commands\RunCommand;
use Cpx\Commands\TinkerCommand;
use Cpx\Commands\UpdateCommand;
use Cpx\Commands\UpgradeCommand;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('cpx', $this->resolveVersion());
        $this->setAutoExit(false);

        $this->registerCommands();
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $input ??= new ArgvInput;

        if ($input instanceof ArgvInput && $this->shouldRunPackageFallback($input)) {
            $input = new ArgvInput(['cpx', RunCommand::NAME, '--', ...$input->getRawTokens()]);
        }

        return parent::run($input, $output);
    }

    protected function getCommandName(InputInterface $input): ?string
    {
        $command = parent::getCommandName($input);

        return $command === null || $this->has($command)
            ? $command
            : RunCommand::NAME;
    }

    private function registerCommands(): void
    {
        $this->addCommands([
            new ListCommand,
            new AliasesCommand,
            new CleanCommand,
            new UpdateCommand,
            new UpgradeCommand,
            new ExecCommand,
            new TinkerCommand,
            new RunCommand,
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
