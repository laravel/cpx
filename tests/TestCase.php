<?php

namespace Tests;

use Laravel\Prompts\Prompt;
use Laravel\Prompts\Terminal;
use PHPUnit\Framework\TestCase as BaseTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionProperty;

abstract class TestCase extends BaseTestCase
{
    /** @var array<string, string|false> */
    private array $environment = [];

    /** @var array<string, string|null> */
    private array $server = [];

    /** @var list<string> */
    private array $temporaryDirectories = [];

    private ?string $workingDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();

        Prompt::interactive(false);

        (new ReflectionProperty(Prompt::class, 'terminal'))->setValue(null, new Terminal);
    }

    protected function tearDown(): void
    {
        if ($this->workingDirectory !== null) {
            chdir($this->workingDirectory);
        }

        foreach (array_reverse($this->temporaryDirectories) as $directory) {
            $this->deleteDirectory($directory);
        }

        foreach ($this->environment as $name => $value) {
            $value === false ? putenv($name) : putenv("{$name}={$value}");
        }

        foreach ($this->server as $name => $value) {
            if ($value === null) {
                unset($_SERVER[$name]);

                continue;
            }

            $_SERVER[$name] = $value;
        }

        parent::tearDown();
    }

    protected function temporaryDirectory(string $prefix = 'cpx-test'): string
    {
        $directory = sys_get_temp_dir().'/'.$prefix.'-'.bin2hex(random_bytes(8));

        mkdir($directory, 0755, true);

        $this->temporaryDirectories[] = $directory;

        return $directory;
    }

    protected function useIsolatedComposerHome(): string
    {
        $home = $this->temporaryDirectory('cpx-home');
        $composerHome = "{$home}/composer";

        mkdir($composerHome, 0755, true);

        $this->setEnvironmentVariable('HOME', $home);
        $this->setEnvironmentVariable('COMPOSER_HOME', $composerHome);

        return $composerHome;
    }

    protected function setEnvironmentVariable(string $name, string $value): void
    {
        if (! array_key_exists($name, $this->environment)) {
            $this->environment[$name] = getenv($name);
        }

        if (! array_key_exists($name, $this->server)) {
            $this->server[$name] = $_SERVER[$name] ?? null;
        }

        putenv("{$name}={$value}");
        $_SERVER[$name] = $value;
    }

    protected function useWorkingDirectory(string $directory): void
    {
        $this->workingDirectory ??= getcwd() ?: null;

        chdir($directory);
    }

    protected function prepareLocalProject(?string $binDir = null): string
    {
        $root = $this->temporaryDirectory('cpx-project');

        $composer = $binDir === null ? [] : ['config' => ['bin-dir' => $binDir]];
        file_put_contents("{$root}/composer.json", json_encode($composer, JSON_THROW_ON_ERROR));

        $this->useWorkingDirectory($root);

        return $root;
    }

    protected function writeLocalBinary(string $root, string $name, string $contents, ?string $binDir = null): string
    {
        $directory = "{$root}/".($binDir ?? 'vendor/bin');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = "{$directory}/{$name}";
        writeExecutable($path, $contents);

        return $path;
    }

    /**
     * @param  list<string>  $bins
     */
    protected function installLocalPackage(string $root, string $package, array $bins, ?string $version = null): void
    {
        [$vendor, $name] = explode('/', $package);
        $directory = "{$root}/vendor/{$vendor}/{$name}";

        mkdir($directory, 0755, true);
        file_put_contents("{$directory}/composer.json", json_encode(['bin' => $bins], JSON_THROW_ON_ERROR));

        if ($version === null) {
            return;
        }

        $this->recordInstalledVersion($root, $package, $version);
    }

    private function recordInstalledVersion(string $root, string $package, string $version): void
    {
        $composerDir = "{$root}/vendor/composer";

        if (! is_dir($composerDir)) {
            mkdir($composerDir, 0755, true);
        }

        $installedFile = "{$composerDir}/installed.json";
        $installed = ['packages' => []];

        if (is_file($installedFile)) {
            $decoded = json_decode((string) file_get_contents($installedFile), true);

            if (is_array($decoded) && isset($decoded['packages']) && is_array($decoded['packages'])) {
                $installed = $decoded;
            }
        }

        $installed['packages'][] = ['name' => $package, 'version' => $version];

        file_put_contents($installedFile, json_encode($installed, JSON_THROW_ON_ERROR));
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
