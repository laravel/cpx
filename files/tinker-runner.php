<?php

use Cpx\Runtime\Context;
use Cpx\Runtime\ExecVariable;
use Cpx\Runtime\PhpExecutionHelper;
use Psy\Configuration;
use Psy\Output\ShellOutput;
use Psy\Shell;

require __DIR__.'/child-runtime.php';

$__cpxVariables = PhpExecutionHelper::prepare(Context::fromEnvironment());

// Prefer the project's own psysh; fall back to the cached package.
if (! class_exists(Shell::class) && ExecVariable::PsyshAutoload->get() !== false) {
    require ExecVariable::PsyshAutoload->get();
}

$__cpxExecute = null;
$__cpxIncludes = [];
$__cpxTokens = array_slice($argv, 1);
$__cpxTokenCount = count($__cpxTokens);

for ($__cpxIndex = 0; $__cpxIndex < $__cpxTokenCount; $__cpxIndex++) {
    $__cpxToken = $__cpxTokens[$__cpxIndex];

    if ($__cpxToken === '--execute') {
        $__cpxExecute = $__cpxTokens[++$__cpxIndex] ?? '';

        continue;
    }

    if (str_starts_with($__cpxToken, '--execute=')) {
        $__cpxExecute = substr($__cpxToken, strlen('--execute='));

        continue;
    }

    if (str_starts_with($__cpxToken, '-')) {
        fwrite(STDERR, "Ignoring unsupported option \"{$__cpxToken}\"; the bundled PsySH shell supports --execute and include files only.".PHP_EOL);

        continue;
    }

    $__cpxIncludes[] = $__cpxToken;
}

$__cpxShell = new Shell(new Configuration([
    'updateCheck' => 'never',
    'rawOutput' => $__cpxExecute !== null,
]));
$__cpxShell->setScopeVariables($__cpxVariables);
$__cpxShell->setIncludes($__cpxIncludes);

if ($__cpxExecute !== null) {
    $__cpxShell->setOutput(new ShellOutput);

    try {
        $__cpxShell->execute($__cpxExecute, true);
    } catch (Throwable $__cpxException) {
        fwrite(STDERR, $__cpxException->getMessage().PHP_EOL);

        exit(1);
    }

    exit(0);
}

exit($__cpxShell->run());
