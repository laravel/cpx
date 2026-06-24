<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

abstract class TestCase extends BaseTestCase
{
    /** @var array<string, string|false> */
    private array $environment = [];

    /** @var array<string, string|null> */
    private array $server = [];

    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
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
