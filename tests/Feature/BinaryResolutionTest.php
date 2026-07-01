<?php

test('a package with one binary runs without requiring a binary name', function () {
    $this->useIsolatedComposerHome();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    prepareCachedPackage('vendor/package', ['package'], [
        'package' => "#!/usr/bin/env php\n<?php file_put_contents('{$logFile}', json_encode(array_slice(\$argv, 1), JSON_THROW_ON_ERROR)); exit(0);\n",
    ]);

    [$status] = runCpxCommand(['vendor/package', '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag']);
});

test('a package with multiple binaries uses the first forwarded argument as the binary name', function () {
    $this->useIsolatedComposerHome();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    prepareCachedPackage('vendor/package', ['foo', 'bar'], [
        'foo' => "#!/usr/bin/env php\n<?php exit(99);\n",
        'bar' => "#!/usr/bin/env php\n<?php file_put_contents('{$logFile}', json_encode(array_slice(\$argv, 1), JSON_THROW_ON_ERROR)); exit(0);\n",
    ]);

    [$status] = runCpxCommand(['vendor/package', 'bar', '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag']);
});

test('a package with multiple binaries keeps a positional argument when a binary matches the package name', function () {
    $this->useIsolatedComposerHome();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    prepareCachedPackage('vendor/package', ['package', 'other'], [
        'package' => "#!/usr/bin/env php\n<?php file_put_contents('{$logFile}', json_encode(array_slice(\$argv, 1), JSON_THROW_ON_ERROR)); exit(0);\n",
        'other' => "#!/usr/bin/env php\n<?php exit(99);\n",
    ]);

    [$status] = runCpxCommand(['vendor/package', 'tests/Sub']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['tests/Sub']);
});

test('ambiguous multiple-binary packages list the available binaries', function () {
    $this->useIsolatedComposerHome();

    prepareCachedPackage('vendor/package', ['foo', 'bar'], [
        'foo' => "#!/usr/bin/env php\n<?php exit(0);\n",
        'bar' => "#!/usr/bin/env php\n<?php exit(0);\n",
    ]);

    [$status, $output] = runCpxCommand(['vendor/package']);

    expect($status)->toBe(1)
        ->and($output)->toContain('More than 1 bin command found for vendor/package: foo, bar.');
});
