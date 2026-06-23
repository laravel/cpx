<?php

use Cpx\Composer;
use Cpx\Exceptions\ComposerInstallException;
use Cpx\Metadata;
use Cpx\PhpExecutionHelper;

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

        $hash = md5(implode(' ', $packages));
        $sandboxDir = cpx_path(".exec_cache/{$hash}");

        $metadata = Metadata::open();

        if (! is_dir($sandboxDir)) {
            mkdir($sandboxDir, 0755, true);

            file_put_contents($sandboxDir.'/composer.json', json_encode([
                'require' => new stdClass,
                'config' => [
                    'vendor-dir' => './vendor',
                ],
            ], JSON_PRETTY_PRINT));

            // Run `composer require` for each package
            foreach ($packages as $package) {
                try {
                    Composer::runCommand("require {$package} --no-interaction --quiet", $sandboxDir);
                    $metadata->execCache[$hash]['last_updated'] = time();
                } catch (Exception $e) {
                    throw new ComposerInstallException("Failed to install package: {$package}.");
                }
            }
        } else {
            if (isset($metadata->execCache[$hash]['last_updated']) && time() - $metadata->execCache[$hash]['last_updated'] >= 3600) {
                // Composer update was not run within the last hour
                try {
                    Composer::runCommand('update --no-interaction --quiet', $sandboxDir);
                    $metadata->execCache[$hash]['last_updated'] = time();
                } catch (Exception $e) {
                    // Update failed, let's just use the existing folder.
                }
            }
        }

        $metadata->execCache[$hash]['packages'] = $packages;
        $metadata->execCache[$hash]['last_run'] = time();
        $metadata->save();

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
        $home = $_SERVER['HOME'] ?? __DIR__;

        return "{$home}/.cpx/".trim($path, '/');
    }
}
