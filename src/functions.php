<?php

declare(strict_types=1);

use Cpx\Exceptions\ComposerInstallException;
use Cpx\Packages\ExecSandbox;

if (! function_exists('composer_require')) {
    /**
     * Dynamically requires Composer packages in a sandboxed environment.
     *
     * @param  string  ...$packages  List of packages to require in the format vendor/package[:version].
     *
     * @throws ComposerInstallException If the Composer require command fails.
     */
    function composer_require(string ...$packages): void
    {
        ExecSandbox::forPackages($packages)->load();
    }
}

if (! function_exists('cpx_path')) {
    function cpx_path(string $path = ''): string
    {
        $composerHome = $_SERVER['COMPOSER_HOME'] ?? getenv('COMPOSER_HOME');

        if (! is_string($composerHome) || $composerHome === '') {
            $home = $_SERVER['HOME'] ?? null;
            $composerHome = is_string($home) && $home !== '' ? $home : __DIR__;
        }

        return "{$composerHome}/.cpx/".trim($path, '/');
    }
}
