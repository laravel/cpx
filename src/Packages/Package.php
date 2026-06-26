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
use Symfony\Component\Console\Command\Command;

class Package
{
    protected function __construct(
        public string $vendor,
        public string $name,
        public ?string $version = null,
    ) {}

    public function __toString(): string
    {
        return $this->fullPackageString();
    }

    public static function parse(string $str): self
    {
        if (empty($str)) {
            throw new InvalidArgumentException('A package name must be provided.');
        }

        if (preg_match('/\A(?<vendor>[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?)\/(?<name>[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?)(?::(?<version>(?![.]+\z)[a-zA-Z0-9_.@~^*<>!=|,-]+))?\z/', $str, $matches) !== 1) {
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

    public function versionName(): string
    {
        return $this->version ?? 'latest';
    }

    public function fullPackageString(): string
    {
        return "{$this->vendor}/{$this->name}"
            .($this->version ? ':'.$this->version : '');
    }

    public function delete(): void
    {
        Filesystem::deleteDirectory(cpx_path($this->folder()));
    }

    public function runCommand(PackageInvocation $invocation, bool $autoUpdate = true): int
    {
        $installDir = $this->installOrUpdatePackage($autoUpdate);
        $packageDir = "{$installDir}/vendor/{$this->vendor}/{$this->name}";
        $binScripts = ComposerRunner::detectBinFromComposer($packageDir);

        if (empty($binScripts)) {
            echo "No bin command found in {$this}.".PHP_EOL;

            return Command::FAILURE;
        }

        $binScripts = Arr::mapWithKeys(fn (int $_, string $value): array => [basename($value) => $value], $binScripts);
        $resolved = $this->resolveBinCommand($binScripts, $invocation);

        if ($resolved === null) {
            echo "More than 1 bin command found for {$this}: ".implode(', ', array_keys($binScripts)).'.'.PHP_EOL;

            return Command::FAILURE;
        }

        [$command, $invocation] = $resolved;
        $binPath = "{$packageDir}/{$command}";

        if (! file_exists($binPath)) {
            echo 'Command '.basename($command)." not found in {$this}.".PHP_EOL;

            return Command::FAILURE;
        }

        Metadata::open()->recordRun($this)->save();
        printColor('Running '.basename($command)." from {$this}");

        return (new ProcessRunner)->run([$binPath, ...$invocation->forwardedTokens()]);
    }

    public function installOrUpdatePackage(bool $updateCheck = true): string
    {
        $installDir = cpx_path($this->folder());

        if (! is_dir($installDir)) {
            mkdir($installDir, 0755, true);
        }

        match (true) {
            ! is_dir("{$installDir}/vendor") => $this->installPackage($installDir),
            $updateCheck && $this->shouldCheckForUpdates() => $this->updatePackage($installDir),
            default => printColor("{$this} is already installed and doesn't need updating."),
        };

        return $installDir;
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

        $lastCheck = strtotime($lastUpdatedAt);

        return $lastCheck === false || (time() - $lastCheck) > 3600;
    }

    /**
     * @param  array<string, string>  $binScripts
     * @return array{0: string, 1: PackageInvocation}|null
     */
    private function resolveBinCommand(array $binScripts, PackageInvocation $invocation): ?array
    {
        if (count($binScripts) === 1) {
            return [$binScripts[array_key_first($binScripts)], $invocation];
        }

        $possibleCommands = array_values(array_unique(array_filter([
            $invocation->target,
            $invocation->firstForwardedToken(),
            $this->name,
        ])));

        foreach ($possibleCommands as $possibleCommand) {
            $command = $binScripts[$possibleCommand] ?? null;

            if ($command === null && in_array($possibleCommand, $binScripts, true)) {
                $command = $possibleCommand;
            }

            if ($command === null) {
                continue;
            }

            return $invocation->firstForwardedToken() === $possibleCommand
                ? [$command, $invocation->withoutFirstForwardedToken()]
                : [$command, $invocation];
        }

        return null;
    }

    private function installPackage(string $installDir): void
    {
        printColor("Installing {$this}...");
        file_put_contents("{$installDir}/composer.json", json_encode([
            'name' => "cpx-{$this->vendor}/cpx-{$this->name}",
            'version' => '1.0.0',
            'config' => [
                'allow-plugins' => true,
            ],
        ]));

        ComposerRunner::run(['require', $this->fullPackageString()], $installDir);
        Metadata::open()->recordUpdate($this)->save();
    }

    private function updatePackage(string $installDir): void
    {
        printColor("Checking for updates for {$this}...");
        $previousVersion = ComposerRunner::getCurrentVersion($installDir);
        ComposerRunner::run(['update'], $installDir);
        $newVersion = ComposerRunner::getCurrentVersion($installDir);

        if ($previousVersion !== $newVersion) {
            printColor("{$this} was upgraded from {$previousVersion} to {$newVersion}.");
        } else {
            printColor("{$this} is already up-to-date.");
        }

        Metadata::open()->recordUpdate($this)->save();
    }
}
