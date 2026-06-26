<?php

declare(strict_types=1);

namespace Cpx;

class PhpExecutionHelper
{
    public static ClassAliasAutoloader $classAliasAutoloader;

    public static function init(
        string $path,
        bool $shouldFindAutoloader = true,
        bool $shouldLoadLaravelBootstrap = true,
        bool $shouldAliasClasses = true,
        bool $shouldBeVerbose = false,
    ): void {
        if (! $shouldFindAutoloader) {
            return;
        }

        $autoloadRootDirectory = $path;

        $autoloadFileSuffix = '/vendor/autoload.php';
        $autoloadFile = $autoloadRootDirectory.$autoloadFileSuffix;

        while (! file_exists($autoloadFile)) {
            $parentDirectory = realpath(dirname($autoloadRootDirectory));

            if ($parentDirectory === false) {
                break;
            }

            $autoloadRootDirectory = $parentDirectory;
            $autoloadFile = $autoloadRootDirectory.$autoloadFileSuffix;

            if ($autoloadRootDirectory === '/') {
                break;
            }
        }

        if (file_exists($autoloadFile)) {
            if ($shouldBeVerbose) {
                echo "Found autoload file at '{$autoloadFile}'".PHP_EOL;
            }

            require_once $autoloadFile;

            if ($shouldLoadLaravelBootstrap && file_exists($autoloadRootDirectory.'/bootstrap/app.php')) {
                if ($shouldBeVerbose) {
                    echo "Found Laravel bootstrap file at '{$autoloadRootDirectory}/bootstrap/app.php'".PHP_EOL;
                }

                if (! defined('LARAVEL_START')) {
                    define('LARAVEL_START', microtime(true));
                }

                require_once $autoloadRootDirectory.'/bootstrap/app.php';
            }

            if ($shouldAliasClasses) {
                if ($shouldBeVerbose) {
                    echo 'Aliasing classes'.PHP_EOL;
                }

                static::getClassAliasAutoloader($shouldBeVerbose)->addAliases($autoloadRootDirectory);
                spl_autoload_register(static::getClassAliasAutoloader($shouldBeVerbose)->aliasClass(...));
            }
        }
    }

    public static function getClassAliasAutoloader(bool $shouldBeVerbose = false): ClassAliasAutoloader
    {
        return static::$classAliasAutoloader ??= new ClassAliasAutoloader($shouldBeVerbose);
    }
}
