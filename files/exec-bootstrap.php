<?php

use Cpx\Runtime\Context;
use Cpx\Runtime\PhpExecutionHelper;

/**
 * Child entry point for `cpx exec`. Loads only the dependency-free Cpx\Runtime
 * slice, the project's own autoloader, and then the user's code at top-level
 * scope so exposed variables ($app, $kernel, ...) become real globals.
 */
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Cpx\\Runtime\\')) {
        require __DIR__.'/../src/Runtime/'.substr($class, strlen('Cpx\\Runtime\\')).'.php';
    }
});

$__cpxVariables = PhpExecutionHelper::prepare(Context::fromEnvironment());

extract($__cpxVariables);
unset($__cpxVariables);

if (getenv('CPX_EXEC_CODE') !== false && getenv('CPX_EXEC_CODE') !== '') {
    eval((string) getenv('CPX_EXEC_CODE'));

    return;
}

require getenv('CPX_EXEC_FILE');
