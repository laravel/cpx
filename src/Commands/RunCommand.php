<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Input\PackageInvocation;
use Cpx\Packages\LocalBinaryResolver;
use Cpx\Packages\Package;
use Cpx\Packages\PackageAliases;
use Cpx\Packages\ResolvedBin;
use Cpx\Packages\TargetKind;
use Cpx\Process\ProcessRunner;
use InvalidArgumentException;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

/**
 * Runs a non-built-in cpx target by resolving it to a local PHP file, a project
 * binary, a package alias, or a vendor/package and executing it.
 */
#[AsCommand(
    name: self::NAME,
    hidden: true,
)]
class RunCommand extends SymfonyCommand
{
    public const NAME = '__cpx_run';

    public function __construct(
        private LocalBinaryResolver $localBinaryResolver = new LocalBinaryResolver,
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
        Prompt::setOutput($output);

        $tokens = $input instanceof ArgvInput ? $input->getRawTokens() : [];

        if (($tokens[0] ?? null) === self::NAME) {
            array_shift($tokens);
        }

        if (($tokens[0] ?? null) === '--') {
            array_shift($tokens);
        }

        $remote = ($tokens[0] ?? null) === '--remote';

        if ($remote) {
            array_shift($tokens);
        }

        try {
            return $this->invoke(PackageInvocation::fromRawTokens($tokens), $output, $remote);
        } catch (InvalidArgumentException $e) {
            error($e->getMessage());

            return SymfonyCommand::FAILURE;
        }
    }

    private function invoke(PackageInvocation $invocation, OutputInterface $output, bool $remote): int
    {
        if ($this->isFile($invocation->target)) {
            return (new ExecCommand)->run($this->fileInput($invocation), $output);
        }

        $resolved = $remote ? null : $this->localBinaryResolver->resolve($invocation);

        if ($resolved !== null) {
            return $this->runLocal($resolved);
        }

        return match (TargetKind::of($invocation->target)) {
            TargetKind::Alias => Package::parse(PackageAliases::all()[$invocation->target]->package)->runCommand($invocation),
            TargetKind::Package => $this->runPackage($invocation),
            TargetKind::Bare => $this->unrecognised($invocation->target),
        };
    }

    private function runPackage(PackageInvocation $invocation): int
    {
        try {
            return Package::parse($invocation->target)->runCommand($invocation);
        } catch (InvalidArgumentException) {
            return $this->unrecognised($invocation->target);
        }
    }

    private function unrecognised(string $target): int
    {
        error("Unrecognised command {$target}");

        return SymfonyCommand::FAILURE;
    }

    private function runLocal(ResolvedBin $resolved): int
    {
        info('Running '.basename($resolved->command)." from {$resolved->command}");

        return (new ProcessRunner)->run([$resolved->command, ...$resolved->invocation->forwardedTokens()]);
    }

    private function isFile(string $path): bool
    {
        $realPath = realpath($path);

        return $realPath !== false && file_exists($realPath) && ! is_dir($realPath);
    }

    private function fileInput(PackageInvocation $invocation): ArrayInput
    {
        $input = ['file' => $invocation->target];

        foreach (['find-autoloader', 'load-laravel-bootstrap', 'alias-classes'] as $option) {
            if ($invocation->hasOption($option)) {
                $input["--{$option}"] = filter_var($invocation->option($option), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            }
        }

        return new ArrayInput($input);
    }
}
