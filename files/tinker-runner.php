<?php

use Cpx\Runtime\Context;
use Cpx\Runtime\PhpExecutionHelper;
use Psy\Configuration;
use Psy\Shell;

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Cpx\\Runtime\\')) {
        require __DIR__.'/../src/Runtime/'.substr($class, strlen('Cpx\\Runtime\\')).'.php';
    }
});

$__cpxVariables = PhpExecutionHelper::prepare(Context::fromEnvironment());

// Prefer the project's own psysh; fall back to the cached package.
if (! class_exists(Shell::class) && getenv('CPX_TINKER_PSYSH_AUTOLOAD') !== false) {
    require getenv('CPX_TINKER_PSYSH_AUTOLOAD');
}

$__cpxShell = new Shell(new Configuration(['updateCheck' => 'never']));
$__cpxShell->setScopeVariables($__cpxVariables);
$__cpxShell->setIncludes(array_values(array_filter(
    array_slice($argv, 1),
    static fn (string $token): bool => ! str_starts_with($token, '-'),
)));

exit($__cpxShell->run());
