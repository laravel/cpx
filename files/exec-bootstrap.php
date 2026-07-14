<?php

use Cpx\Runtime\Context;
use Cpx\Runtime\ExecVariable;
use Cpx\Runtime\PhpExecutionHelper;

/**
 * Child entry point for `cpx exec`. Loads only the dependency-free Cpx\Runtime
 * slice, the project's own autoloader, and then the user's code at top-level
 * scope so exposed variables ($app, $kernel, ...) become real globals.
 */
require __DIR__.'/child-runtime.php';

$__cpxVariables = PhpExecutionHelper::prepare(Context::fromEnvironment());

extract($__cpxVariables);
unset($__cpxVariables);

$__cpxCode = (string) ExecVariable::Code->get();

if ($__cpxCode !== '') {
    eval($__cpxCode);

    return;
}

unset($__cpxCode);

require ExecVariable::File->get();
