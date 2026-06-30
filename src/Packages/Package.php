<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Cpx\Cache\Metadata;
use Cpx\Composer\ComposerRunner;
use Cpx\Input\PackageInvocation;
use Cpx\Process\ProcessRunner;
use Cpx\Support\Arr;
use Cpx\Support\Filesystem;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

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
    ) {
        //
    }

    public function __toString(): string
    {
        return $this->fullPackageString();
    }

    public static function parse(string $str): self
    {
        if (empty($str)) {
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

    public function delete(): void
    {
        Filesystem::deleteDirectory($this->installPath());
    }

    public function runCommand(PackageInvocation $invocation, OutputInterface $output, bool $autoUpdate = true): int
    {
        $installDir = $this->installOrUpdatePackage($output, $autoUpdate);
        $packageDir = "{$installDir}/vendor/{$this->vendor}/{$this->name}";
        $binScripts = ComposerRunner::detectBinFromComposer($packageDir);

        if (empty($binScripts)) {
            $output->writeln("<error>No bin command found in {$this}.</error>");

            return Command::FAILURE;
        }

        $binScripts = Arr::mapWithKeys(fn (int $_, string $value): array => [basename($value) => $value], $binScripts);
        $resolved = $this->resolveBinCommand($binScripts, $invocation);

        if ($resolved === null) {
            $output->writeln("<error>More than 1 bin command found for {$this}: ".implode(', ', array_keys($binScripts)).'.</error>');

            return Command::FAILURE;
        }

        $binPath = "{$packageDir}/{$resolved->command}";

        if (! file_exists($binPath)) {
            $output->writeln('<error>Command '.basename($resolved->command)." not found in {$this}.</error>");

            return Command::FAILURE;
        }

        Metadata::transaction(fn (Metadata $metadata) => $metadata->recordRun($this));
        $output->writeln('<info>Running '.basename($resolved->command)." from {$this}</info>");

        return (new ProcessRunner)->run([$binPath, ...$resolved->invocation->forwardedTokens()]);
    }

    public function installOrUpdatePackage(OutputInterface $output, bool $updateCheck = true): string
    {
        $installDir = $this->installPath();

        match (true) {
            ! $this->isInstalled() => $this->installPackage($output, $installDir),
            $updateCheck && $this->shouldCheckForUpdates() => $this->updatePackage($output, $installDir),
            default => $output->writeln("<info>{$this} is already installed and doesn't need updating.</info>"),
        };

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
     * @param  array<string, string>  $binScripts
     */
    private function resolveBinCommand(array $binScripts, PackageInvocation $invocation): ?ResolvedBin
    {
        if (count($binScripts) === 1) {
            return new ResolvedBin($binScripts[array_key_first($binScripts)], $invocation);
        }

        $candidates = array_values(array_unique(array_filter([
            $invocation->target,
            $invocation->firstForwardedToken(),
            $this->name,
        ])));

        foreach ($candidates as $candidate) {
            $command = $this->matchBin($binScripts, $candidate);

            if ($command === null) {
                continue;
            }

            return $invocation->firstForwardedToken() === $candidate
                ? new ResolvedBin($command, $invocation->withoutFirstForwardedToken())
                : new ResolvedBin($command, $invocation);
        }

        return null;
    }

    /**
     * @param  array<string, string>  $binScripts
     */
    private function matchBin(array $binScripts, string $candidate): ?string
    {
        if (array_key_exists($candidate, $binScripts)) {
            return $binScripts[$candidate];
        }

        return in_array($candidate, $binScripts, true) ? $candidate : null;
    }

    private function installPackage(OutputInterface $output, string $installDir): void
    {
        $output->writeln("<info>Installing {$this}...</info>");

        $cacheRoot = cpx_path();
        Filesystem::ensureDirectory($cacheRoot);

        $stagingDir = "{$installDir}.installing.".getmypid();
        $this->stageInstall($stagingDir, $cacheRoot);

        Filesystem::deleteDirectory($installDir);

        if (! rename($stagingDir, $installDir)) {
            Filesystem::deleteDirectoryWithin($stagingDir, $cacheRoot);

            throw new RuntimeException("Unable to finalize the installation of {$this}.");
        }

        Metadata::transaction(fn (Metadata $metadata) => $metadata->recordUpdate($this));
    }

    private function stageInstall(string $stagingDir, string $cacheRoot): void
    {
        Filesystem::deleteDirectoryWithin($stagingDir, $cacheRoot);
        Filesystem::ensureDirectory($stagingDir);

        file_put_contents("{$stagingDir}/composer.json", json_encode([
            'name' => "cpx-{$this->vendor}/cpx-{$this->name}",
            'version' => self::SCAFFOLD_VERSION,
            'config' => [
                // Requested packages may ship Composer plugins (binaries, installers).
                'allow-plugins' => true,
            ],
        ]));

        try {
            ComposerRunner::run(['require', $this->fullPackageString()], $stagingDir);
        } catch (Throwable $exception) {
            Filesystem::deleteDirectoryWithin($stagingDir, $cacheRoot);

            throw $exception;
        }
    }

    private function updatePackage(OutputInterface $output, string $installDir): void
    {
        $output->writeln("<info>Checking for updates for {$this}...</info>");
        $previousVersion = ComposerRunner::getCurrentVersion($installDir);
        ComposerRunner::run(['update'], $installDir);
        $newVersion = ComposerRunner::getCurrentVersion($installDir);

        if ($previousVersion !== $newVersion) {
            $output->writeln("<info>{$this} was upgraded from {$previousVersion} to {$newVersion}.</info>");
        } else {
            $output->writeln("<info>{$this} is already up-to-date.</info>");
        }

        Metadata::transaction(fn (Metadata $metadata) => $metadata->recordUpdate($this));
    }
}
