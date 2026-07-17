<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Commands\Concerns\OutputsJson;
use Cpx\Exceptions\PackageNotFoundException;
use Cpx\Packages\LocalPackage;
use Cpx\Packages\Package;
use Cpx\Packages\UserAliases;
use InvalidArgumentException;
use Laravel\Prompts\Exceptions\NonInteractiveValidationException;
use Laravel\Prompts\Output\BufferedConsoleOutput;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\callout;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\task;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[AsCommand(
    name: 'alias',
    description: 'Create a shortcut command for a Composer package',
)]
class AliasCommand extends Command
{
    use OutputsJson;

    protected function configure(): void
    {
        $this->addArgument('package', InputArgument::OPTIONAL, 'The package to alias, e.g. <vendor>/<package>[:version]');
        $this->addArgument('name', InputArgument::OPTIONAL, 'The alias name to run the package as, e.g. "cpx <name>"');
        $this->addOption('bin', null, InputOption::VALUE_REQUIRED, 'The binary to run when the package exposes more than one');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite an existing alias without confirmation');
        $this->addJsonOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = $this->wantsJson($input);

        if ($json) {
            // Package installation renders task progress even when non-interactive.
            Prompt::setOutput(new BufferedConsoleOutput);
        }

        try {
            $package = $this->resolvePackage($input);
            $name = $this->resolveName($input, $package);

            $aliases = UserAliases::open();

            if (! $this->confirmOverwrite($input, $aliases, $name, $json)) {
                info("Alias \"{$name}\" was left unchanged.");

                return self::SUCCESS;
            }

            $package = $this->resolveBinary($input, $package);
        } catch (PackageNotFoundException $e) {
            if ($json) {
                return $this->outputJsonFailure($output, $e->getMessage());
            }

            $e->render();

            return self::FAILURE;
        } catch (InvalidArgumentException|NonInteractiveValidationException $e) {
            if ($json) {
                return $this->outputJsonFailure($output, $e->getMessage());
            }

            error($e->getMessage());

            return self::FAILURE;
        }

        if ($json) {
            $aliases->put($name, $package)->save();

            return $this->outputJsonSuccess($output, [
                'alias' => $name,
                'package' => $package->displayString(),
            ]);
        }

        task(
            label: 'Creating alias',
            callback: fn () => $aliases->put($name, $package)->save(),
            keepSummary: true,
        );

        callout('Alias created', "`cpx {$name}` now runs {$package->displayString()}");

        return self::SUCCESS;
    }

    private function resolveBinary(InputInterface $input, Package $package): Package
    {
        $binaries = array_keys($package->binaries($package->installOrUpdatePackage()));

        if ($binaries === []) {
            throw new InvalidArgumentException("{$package} does not provide any binaries.");
        }

        if (($bin = $input->getOption('bin')) !== null) {
            return $package->withBin($this->ensureBinaryExists($package, $bin, $binaries));
        }

        if (count($binaries) === 1) {
            return $package;
        }

        return $package->withBin($this->chooseBinary($input, $package, $binaries));
    }

    /**
     * @param  list<string>  $binaries
     */
    private function ensureBinaryExists(Package $package, string $bin, array $binaries): string
    {
        if (! in_array($bin, $binaries)) {
            throw new InvalidArgumentException(
                "\"{$bin}\" is not a binary provided by {$package}. Available binaries: ".implode(', ', $binaries).'.',
            );
        }

        return $bin;
    }

    /**
     * @param  list<string>  $binaries
     */
    private function chooseBinary(InputInterface $input, Package $package, array $binaries): string
    {
        if (! $input->isInteractive()) {
            throw new InvalidArgumentException(
                "{$package} exposes multiple binaries (".implode(', ', $binaries).'). Choose one with the --bin option.',
            );
        }

        return (string) select(
            label: "Which binary of {$package} would you like to alias?",
            options: $binaries,
            default: in_array($package->name, $binaries, true) ? $package->name : null,
        );
    }

    private function confirmOverwrite(InputInterface $input, UserAliases $aliases, string $name, bool $json): bool
    {
        $current = $aliases->find($name);

        if ($current === null || $input->getOption('force')) {
            return true;
        }

        if ($json) {
            throw new InvalidArgumentException("The alias \"{$name}\" already exists. Use the --force option to overwrite it.");
        }

        warning("The alias \"{$name}\" is currently mapped to {$current->displayString()}.");

        return confirm(
            label: "Do you want to overwrite the \"{$name}\" alias?",
            default: true,
        );
    }

    private function resolvePackage(InputInterface $input): Package
    {
        return $this->parsePackage($this->resolvePackageName($input));
    }

    private function resolvePackageName(InputInterface $input): string
    {
        if ($package = $input->getArgument('package')) {
            if ($error = $this->validatePackage($package)) {
                throw new InvalidArgumentException($error);
            }

            return $package;
        }

        return text(
            label: 'Which package would you like to alias?',
            placeholder: '<vendor>/<package>[:version]',
            required: 'A package name must be provided.',
            validate: $this->validatePackage(...),
        );
    }

    private function validatePackage(string $value): ?string
    {
        try {
            $this->parsePackage($value);

            return null;
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
    }

    private function parsePackage(string $value): Package
    {
        return LocalPackage::supports($value)
            ? LocalPackage::parse($value)
            : Package::parse($value);
    }

    private function resolveName(InputInterface $input, Package $package): string
    {
        if ($name = $input->getArgument('name')) {
            if ($error = $this->validateName($name)) {
                throw new InvalidArgumentException($error);
            }

            return $name;
        }

        return text(
            label: 'What should the alias be called?',
            default: $package->name,
            validate: $this->validateName(...),
        );
    }

    private function validateName(string $name): ?string
    {
        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/', $name) !== 1) {
            return 'An alias name may only contain letters, numbers, dots, dashes and underscores.';
        }

        if ($this->getApplication()?->has($name) === true) {
            return "\"{$name}\" is already a cpx command and cannot be used as an alias name.";
        }

        return null;
    }
}
