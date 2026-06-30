<?php

declare(strict_types=1);

use Cpx\Cache\ExecSandboxMetadata;
use Cpx\Cache\Metadata;
use Cpx\Composer\ComposerRunner;
use Cpx\Exceptions\ComposerInstallException;
use Cpx\Runtime\PhpExecutionHelper;

if (! function_exists('composer_require')) {
    /**
     * Dynamically requires Composer packages in a sandboxed environment.
     *
     * @param  string  ...$packages  List of packages to require in the format vendor/package[:version].
     *
     * @throws Exception If the Composer require command fails.
     */
    function composer_require(string ...$packages): void
    {
        sort($packages);

        $key = hash('sha256', implode(' ', $packages));
        $sandboxDir = cpx_path(".exec_cache/{$key}");

        $sandbox = Metadata::open()->execCache[$key] ?? new ExecSandboxMetadata(key: $key);

        if (! is_dir($sandboxDir)) {
            mkdir($sandboxDir, 0755, true);

            file_put_contents($sandboxDir.'/composer.json', json_encode([
                'require' => new stdClass,
                'config' => [
                    'vendor-dir' => './vendor',
                ],
            ], JSON_PRETTY_PRINT));

            foreach ($packages as $package) {
                try {
                    ComposerRunner::run(['require', $package], $sandboxDir);
                    $sandbox->lastUpdatedAt = time();
                } catch (Exception) {
                    throw new ComposerInstallException("Failed to install package: {$package}.");
                }
            }
        } elseif ($sandbox->lastUpdatedAt !== null && time() - $sandbox->lastUpdatedAt >= Metadata::UPDATE_CHECK_INTERVAL) {
            try {
                ComposerRunner::run(['update'], $sandboxDir);
                $sandbox->lastUpdatedAt = time();
            } catch (Exception) {
                // Update failed, keep using the existing sandbox.
            }
        }

        $sandbox->packages = $packages;
        $sandbox->lastRunAt = time();

        Metadata::transaction(function (Metadata $metadata) use ($key, $sandbox): void {
            $metadata->execCache[$key] = $sandbox;
        });

        $autoloadFile = "{$sandboxDir}/vendor/autoload.php";

        if (! file_exists($autoloadFile)) {
            throw new Exception("Autoload file not found in {$sandboxDir}/vendor/. Composer installation may have failed.");
        }

        if (isset(PhpExecutionHelper::$classAliasAutoloader)) {
            PhpExecutionHelper::$classAliasAutoloader->addAliases($sandboxDir);
        }

        require_once $autoloadFile;
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
