<?php

use Cpx\Runtime\Context;
use Cpx\Runtime\ExecVariable;
use Cpx\Runtime\PhpExecutionHelper;
use Psy\Configuration;
use Psy\Shell;

require __DIR__.'/child-runtime.php';

$__cpxVariables = PhpExecutionHelper::prepare(Context::fromEnvironment());

// Prefer the project's own psysh; fall back to the cached package.
if (! class_exists(Shell::class) && ExecVariable::PsyshAutoload->get() !== false) {
    require ExecVariable::PsyshAutoload->get();
}

$__cpxShell = new Shell(new Configuration(['updateCheck' => 'never']));
$__cpxShell->setScopeVariables($__cpxVariables);
$__cpxShell->setIncludes(array_values(array_filter(
    array_slice($argv, 1),
    static fn (string $token): bool => ! str_starts_with($token, '-'),
)));

exit($__cpxShell->run());
