<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Cpx\Cache\ExecSandboxMetadata;
use Cpx\Cache\Metadata;
use Cpx\Composer\ComposerRunner;
use Cpx\Exceptions\ComposerInstallException;
use Cpx\Runtime\PhpExecutionHelper;
use Cpx\Support\Filesystem;
use RuntimeException;
use stdClass;
use Throwable;

class ExecSandbox
{
    /**
     * @param  list<string>  $packages
     */
    protected function __construct(
        public string $key,
        public array $packages,
    ) {
        //
    }

    /**
     * @param  array<string>  $packages
     */
    public static function forPackages(array $packages): self
    {
        sort($packages);

        return new self(hash('sha256', implode(' ', $packages)), $packages);
    }

    public function path(): string
    {
        return ExecSandboxMetadata::pathFor($this->key);
    }

    public function isInstalled(): bool
    {
        return file_exists($this->path().'/vendor/autoload.php');
    }

    public function load(): void
    {
        $path = $this->ensureInstalled();

        if (isset(PhpExecutionHelper::$classAliasAutoloader)) {
            PhpExecutionHelper::$classAliasAutoloader->addAliases($path);
        }

        require_once "{$path}/vendor/autoload.php";
    }

    public function ensureInstalled(): string
    {
        $updatedAt = match (true) {
            ! $this->isInstalled() => $this->install(),
            $this->shouldCheckForUpdates() => $this->update(),
            default => null,
        };

        if (! file_exists($this->path().'/vendor/autoload.php')) {
            throw new ComposerInstallException("Autoload file not found in {$this->path()}/vendor/. Composer installation may have failed.");
        }

        $this->record($updatedAt);

        return $this->path();
    }

    private function install(): int
    {
        $cacheRoot = cpx_path();
        Filesystem::ensureDirectory($cacheRoot);

        $stagingDir = $this->path().'.installing.'.getmypid();
        $this->stageInstall($stagingDir, $cacheRoot);

        try {
            Filesystem::replaceDirectory($stagingDir, $this->path());
        } catch (RuntimeException $exception) {
            Filesystem::deleteDirectoryWithin($stagingDir, $cacheRoot);

            throw new ComposerInstallException("Unable to finalize the exec sandbox for {$this->key}; another process may be holding files under {$this->path()}.", previous: $exception);
        }

        return time();
    }

    private function stageInstall(string $stagingDir, string $cacheRoot): void
    {
        Filesystem::deleteDirectoryWithin($stagingDir, $cacheRoot);
        Filesystem::ensureDirectory($stagingDir);

        file_put_contents("{$stagingDir}/composer.json", json_encode([
            'require' => new stdClass,
            'config' => [
                'vendor-dir' => './vendor',
            ],
        ], JSON_PRETTY_PRINT));

        foreach ($this->packages as $package) {
            try {
                ComposerRunner::run(['require', $package], $stagingDir);
            } catch (Throwable $exception) {
                Filesystem::deleteDirectoryWithin($stagingDir, $cacheRoot);

                throw new ComposerInstallException("Failed to install package: {$package}.", previous: $exception);
            }
        }
    }

    private function update(): ?int
    {
        try {
            ComposerRunner::run(['update'], $this->path());

            return time();
        } catch (Throwable) {
            return null;
        }
    }

    private function shouldCheckForUpdates(): bool
    {
        $lastUpdatedAt = (Metadata::open()->execCache[$this->key] ?? null)?->lastUpdatedAt;

        if ($lastUpdatedAt === null) {
            return true;
        }

        return (time() - $lastUpdatedAt) > Metadata::UPDATE_CHECK_INTERVAL;
    }

    private function record(?int $updatedAt): void
    {
        Metadata::transaction(function (Metadata $metadata) use ($updatedAt): void {
            $sandbox = $metadata->execCache[$this->key] ?? new ExecSandboxMetadata(key: $this->key);
            $sandbox->packages = $this->packages;
            $sandbox->lastRunAt = time();

            if ($updatedAt !== null) {
                $sandbox->lastUpdatedAt = $updatedAt;
            }

            $metadata->execCache[$this->key] = $sandbox;
        });
    }
}
