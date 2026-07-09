<?php

declare(strict_types=1);

use Cpx\Exceptions\ComposerInstallException;
use Cpx\Packages\ExecSandbox;
use Cpx\Support\Filesystem;

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
            return Filesystem::joinPath($cpxHome, $path);
        }

        foreach (['HOME', 'USERPROFILE'] as $variable) {
            $home = $_SERVER[$variable] ?? getenv($variable);

            if (is_string($home) && $home !== '') {
                return Filesystem::joinPath($home, '.cpx', $path);
            }
        }

        $drive = $_SERVER['HOMEDRIVE'] ?? getenv('HOMEDRIVE');
        $homePath = $_SERVER['HOMEPATH'] ?? getenv('HOMEPATH');

        if (is_string($drive) && $drive !== '' && is_string($homePath) && $homePath !== '') {
            return Filesystem::joinPath("{$drive}{$homePath}", '.cpx', $path);
        }

        throw new RuntimeException('Unable to determine the home directory; set the CPX_HOME, HOME, or USERPROFILE environment variable.');
    }
}
