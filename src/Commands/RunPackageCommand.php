<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Input\PackageInvocation;
use Cpx\Packages\PackageCommandRunner;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;

#[AsCommand(
    name: self::NAME,
    hidden: true,
)]
class RunPackageCommand extends SymfonyCommand
{
    public const NAME = '__cpx_run_package';

    public function __construct(
        private PackageCommandRunner $packageCommandRunner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        // Let undeclared package args/options pass through instead of failing validation;
        // execute() reads the raw tokens and forwards them to the package.
        $this->ignoreValidationErrors();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tokens = $input instanceof ArgvInput ? $input->getRawTokens() : [];

        if (($tokens[0] ?? null) === self::NAME) {
            array_shift($tokens);
        }

        if (($tokens[0] ?? null) === '--') {
            array_shift($tokens);
        }

        $skipLocal = ($tokens[0] ?? null) === '--skip-local';

        if ($skipLocal) {
            array_shift($tokens);
        }

        try {
            return $this->packageCommandRunner->run(PackageInvocation::fromRawTokens($tokens), $output, $skipLocal);
        } catch (InvalidArgumentException $e) {
            error($e->getMessage());

            return SymfonyCommand::FAILURE;
        }
    }
}
