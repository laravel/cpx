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
        $cpxHome = $_SERVER['CPX_HOME'] ?? getenv('CPX_HOME');

        if (is_string($cpxHome) && $cpxHome !== '') {
            return rtrim(rtrim($cpxHome, '/').'/'.trim($path, '/'), '/');
        }

        $home = $_SERVER['HOME'] ?? getenv('HOME');

        if (! is_string($home) || $home === '') {
            throw new RuntimeException('Unable to determine the home directory; set the HOME or CPX_HOME environment variable.');
        }

        return rtrim("{$home}/.cpx/".trim($path, '/'), '/');
    }
}
