<?php

use Cpx\Runtime\ComposerRequire;

/**
 * Shared prelude for cpx child processes: registers the dependency-free
 * Cpx\Runtime autoloader and bridges composer_require() to a spawned cpx,
 * keeping the phar's bundled dependencies out of the child.
 */
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Cpx\\Runtime\\')) {
        require __DIR__.'/../src/Runtime/'.substr($class, strlen('Cpx\\Runtime\\')).'.php';
    }
});

if (! function_exists('composer_require')) {
    /**
     * Dynamically requires Composer packages in a sandboxed environment.
     *
     * @param  string  ...$packages  List of packages to require in the format vendor/package[:version].
     */
    function composer_require(string ...$packages): void
    {
        ComposerRequire::load(...$packages);
    }
}
