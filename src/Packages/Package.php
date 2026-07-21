<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Cpx\Cache\Metadata;
use Cpx\Composer\ComposerRunner;
use Cpx\Exceptions\PackageNotFoundException;
use Cpx\Input\PackageInvocation;
use Cpx\Process\ProcessRunner;
use Cpx\Support\Filesystem;
use Cpx\Support\Interactivity;
use Cpx\Support\Result;
use InvalidArgumentException;
use Laravel\Prompts\Exceptions\NonInteractiveValidationException;
use Laravel\Prompts\Support\Logger;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\task;

class Package
{
    /**
     * Composer's official package-name grammar (vendor/name), extended with an optional ":version" constraint.
     */
    private const PACKAGE_PATTERN = '/\A(?<vendor>[a-z0-9](?:[_.-]?[a-z0-9]+)*)\/(?<name>[a-z0-9](?:(?:[_.]?|-{0,2})[a-z0-9]+)*)(?::(?<version>(?![.]+\z)[a-zA-Z0-9_.@~^*<>!=|,-]+))?\z/';

    private const SCAFFOLD_VERSION = '1.0.0';

    protected function __construct(
        public string $vendor,
        public string $name,
        public ?string $version = null,
        public ?string $bin = null,
    ) {
        //
    }

    public function __toString(): string
    {
        return $this->fullPackageString();
    }

    public static function parse(string $str): self
    {
        if ($str === '') {
            throw new InvalidArgumentException('A package name must be provided.');
        }

        if (preg_match(self::PACKAGE_PATTERN, $str, $matches) !== 1) {
            throw new InvalidArgumentException('A package name should be in the format "<vendor>/<package>[:version]".');
        }

        return new self(
            vendor: $matches['vendor'],
            name: $matches['name'],
            version: $matches['version'] ?? null,
        );
    }

    public function folder(): string
    {
        return "{$this->vendor}/{$this->name}/{$this->versionName()}";
    }

    public function installPath(): string
    {
        return cpx_path($this->folder());
    }

    public function versionName(): string
    {
        if ($this->version === null) {
            return 'latest';
        }

        $slug = trim((string) preg_replace('/[^a-z0-9_.]+/i', '-', $this->version), '-');
        $suffix = substr(hash('sha256', $this->version), 0, 12);

        return $slug === '' ? $suffix : "{$slug}-{$suffix}";
    }

    public function fullPackageString(): string
    {
        return "{$this->vendor}/{$this->name}"
            .($this->version ? ':'.$this->version : '');
    }

    public function displayString(): string
    {
        $result = $this->fullPackageString();

        if (! is_null($this->bin)) {
            $result .= " ({$this->bin})";
        }

        return $result;
    }

    public function withBin(?string $bin): self
    {
        $clone = clone $this;
        $clone->bin = $bin;

        return $clone;
    }

    public function packagePath(string $installDir): string
    {
        return "{$installDir}/vendor/{$this->vendor}/{$this->name}";
    }

    /**
     * @return array<string, string>
     */
    public function binaries(string $installDir): array
    {
        return self::mapBinaries(ComposerRunner::detectBinFromComposer($this->packagePath($installDir)));
    }

    public function delete(): void
    {
        Filesystem::deleteDirectory($this->installPath());
    }

    public function runCommand(PackageInvocation $invocation, OutputInterface $output, bool $autoUpdate = true): int
    {
        try {
            $installDir = $this->installOrUpdatePackage($autoUpdate);
        } catch (PackageNotFoundException $exception) {
            if (Interactivity::isInteractive()) {
                $exception->render();

                return Command::FAILURE;
            }

            return Result::failure($output, $exception->getMessage());
        }
        $binScripts = $this->binaries($installDir);

        if ($binScripts === []) {
            return Result::failure($output, "No bin command found in {$this}.");
        }

        $resolved = $this->resolveBinCommand($binScripts, $invocation)
            ?? $this->chooseBinCommand($binScripts, $invocation);

        if ($resolved === null) {
            return Result::failure($output, "More than 1 bin command found for {$this}: ".implode(', ', array_keys($binScripts)).'.');
        }

        $binPath = "{$this->packagePath($installDir)}/{$resolved->command}";

        if (! is_file($binPath)) {
            return Result::failure($output, 'Command '.basename($resolved->command)." not found in {$this}.");
        }

        $this->recordRun();

        info('Running '.basename($resolved->command)." from {$this}");

        return (new ProcessRunner)->run(BinExecutable::commandFor($binPath, $resolved->invocation->forwardedTokens()));
    }

    public function installOrUpdatePackage(bool $updateCheck = true): string
    {
        $installDir = $this->installPath();

        if (! $this->isInstalled()) {
            $this->installPackage($installDir);
        } elseif ($updateCheck && $this->shouldCheckForUpdates()) {
            $this->updatePackage($installDir);
        }

        return $installDir;
    }

    public function isInstalled(): bool
    {
        return file_exists($this->installPath().'/vendor/autoload.php');
    }

    public function shouldCheckForUpdates(): bool
    {
        $metadata = Metadata::open();
        $packageKey = $this->fullPackageString();

        if (! $metadata->hasPackage($this)) {
            return true;
        }

        $lastUpdatedAt = $metadata->packages[$packageKey]->lastUpdatedAt;

        if ($lastUpdatedAt === null) {
            return true;
        }

        return (time() - $lastUpdatedAt) > Metadata::UPDATE_CHECK_INTERVAL;
    }

    /**
     * @return array<string, string>
     */
    protected static function mapBinaries(mixed $declared): array
    {
        $binaries = [];

        foreach ((array) $declared as $binary) {
            if (! is_string($binary) || $binary === '') {
                continue;
            }

            $binaries[basename(Filesystem::normalizePath($binary))] = $binary;
        }

        return $binaries;
    }

    protected function recordRun(): void
    {
        Metadata::transaction(fn (Metadata $metadata) => $metadata->recordRun($this));
    }

    /**
     * @param  array<string, string>  $binScripts
     */
    protected function chooseBinCommand(array $binScripts, PackageInvocation $invocation): ?ResolvedBin
    {
        if (! Interactivity::isInteractive()) {
            return null;
        }

        try {
            $chosen = select(
                label: "Which command would you like to run from {$this}?",
                options: array_keys($binScripts),
            );
        } catch (NonInteractiveValidationException) {
            return null;
        }

        $command = $binScripts[$chosen] ?? null;

        return $command === null ? null : new ResolvedBin($command, $invocation);
    }

    /**
     * @param  array<string, string>  $binScripts
     */
    private function resolveBinCommand(array $binScripts, PackageInvocation $invocation): ?ResolvedBin
    {
        $resolved = BinResolver::resolve($binScripts, $invocation, $this->name, $this->bin);

        if ($resolved === null && $this->bin !== null) {
            throw new RuntimeException("The requested bin command '{$this->bin}' was not found in {$this}.");
        }

        return $resolved;
    }

    private function installPackage(string $installDir): void
    {
        task(
            label: "Installing {$this}",
            callback: function (Logger $logger) use ($installDir): void {
                StagedInstall::run(
                    targetDir: $installDir,
                    scaffold: [
                        'name' => "cpx-{$this->vendor}/cpx-{$this->name}",
                        'version' => self::SCAFFOLD_VERSION,
                        'config' => [
                            // Requested packages may ship Composer plugins (binaries, installers).
                            'allow-plugins' => true,
                        ],
                    ],
                    install: fn (string $stagingDir) => ProcessRunner::withLogger($logger, fn () => ComposerRunner::require($this->fullPackageString(), $stagingDir)),
                    finalizeFailure: fn (RuntimeException $exception): Throwable => new RuntimeException("Unable to finalize the installation of {$this}; another process may be holding files under {$installDir}.", previous: $exception),
                );

                Metadata::transaction(fn (Metadata $metadata) => $metadata->recordUpdate($this));
            },
            keepSummary: true,
        );
    }

    private function updatePackage(string $installDir): void
    {
        task(
            label: "Updating {$this}",
            callback: function (Logger $logger) use ($installDir): void {
                $package = "{$this->vendor}/{$this->name}";
                $previousVersion = ComposerRunner::getCurrentVersion($installDir, $package);
                ProcessRunner::withLogger($logger, fn () => ComposerRunner::run(['update'], $installDir));
                $newVersion = ComposerRunner::getCurrentVersion($installDir, $package);

                if ($previousVersion !== $newVersion) {
                    $logger->label("{$this} was upgraded from {$previousVersion} to {$newVersion}");
                } else {
                    $logger->label("{$this} is already up-to-date");
                }

                Metadata::transaction(fn (Metadata $metadata) => $metadata->recordUpdate($this));
            },
            keepSummary: true,
        );
    }
}
