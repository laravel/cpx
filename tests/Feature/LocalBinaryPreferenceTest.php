<?php

test('it runs a local vendor bin before installing an isolated package', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';
    $this->writeLocalBinary($root, 'pint', argvLoggingBinary($logFile));

    [$status, $output] = runCpxCommand(['pint']);

    expect($status)->toBe(0)
        ->and($output)->toContain('Running pint from')
        ->and(is_dir(cpx_path('laravel/pint')))->toBeFalse()
        ->and(file_exists($logFile))->toBeTrue();
});

test('it runs a local bin for a bare package name', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';
    $this->writeLocalBinary($root, 'phpunit', argvLoggingBinary($logFile));

    [$status, $output] = runCpxCommand(['phpunit', '--filter=Foo']);

    expect($status)->toBe(0)
        ->and($output)->toContain('Running phpunit from')
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--filter=Foo']);
});

test('it discovers a custom composer bin-dir before remote package resolution', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject('tools');
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';
    $this->writeLocalBinary($root, 'pint', argvLoggingBinary($logFile), 'tools');

    [$status] = runCpxCommand(['pint']);

    expect($status)->toBe(0)
        ->and(is_dir(cpx_path('laravel/pint')))->toBeFalse()
        ->and(file_exists($logFile))->toBeTrue();
});

test('local binary execution preserves forwarded arguments and exit codes', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';
    $this->writeLocalBinary($root, 'pint', argvLoggingBinary($logFile, 42));

    [$status] = runCpxCommand(['pint', '--flag', 'a b', '--', '--literal']);

    expect($status)->toBe(42)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe([
            '--flag',
            'a b',
            '--',
            '--literal',
        ]);
});

test('a failing local binary makes cpx exit non-zero', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->writeLocalBinary($root, 'pint', "#!/usr/bin/env php\n<?php exit(17);\n");

    [$status] = runCpxCommand(['pint']);

    expect($status)->toBe(17);
});

test('it runs a locally installed vendor/package bin before installing an isolated package', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'laravel/pint', ['pint']);
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';
    $this->writeLocalBinary($root, 'pint', argvLoggingBinary($logFile));

    [$status, $output] = runCpxCommand(['laravel/pint']);

    expect($status)->toBe(0)
        ->and($output)->toContain('Running pint from')
        ->and(is_dir(cpx_path('laravel/pint')))->toBeFalse()
        ->and(file_exists($logFile))->toBeTrue();
});

test('it prefers the local bin for a version-pinned target the installed version satisfies', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'laravel/pint', ['pint'], 'v2.1.0');
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';
    $this->writeLocalBinary($root, 'pint', argvLoggingBinary($logFile));

    [$status] = runCpxCommand(['laravel/pint:^2.0']);

    expect($status)->toBe(0)
        ->and(is_dir(cpx_path('laravel/pint')))->toBeFalse()
        ->and(file_exists($logFile))->toBeTrue();
});

test('a version-pinned target falls back to the isolated cache when the installed version does not satisfy', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'laravel/pint', ['pint'], 'v1.13.0');
    $localLog = $this->temporaryDirectory('cpx-log').'/local.json';
    $this->writeLocalBinary($root, 'pint', argvLoggingBinary($localLog));

    $isolatedLog = $this->temporaryDirectory('cpx-log').'/isolated.json';
    prepareCachedPackage('laravel/pint', ['pint'], ['pint' => argvLoggingBinary($isolatedLog)], '^2.0');

    [$status, $output] = runCpxCommand(['laravel/pint:^2.0']);

    expect($status)->toBe(0)
        ->and($output)->toContain('from laravel/pint:^2.0')
        ->and(file_exists($isolatedLog))->toBeTrue()
        ->and(file_exists($localLog))->toBeFalse();
});

test('an uninstalled vendor/package target falls back to the isolated cache', function () {
    $this->useIsolatedComposerHome();
    $this->prepareLocalProject();
    $isolatedLog = $this->temporaryDirectory('cpx-log').'/isolated.json';
    prepareCachedPackage('laravel/pint', ['pint'], ['pint' => argvLoggingBinary($isolatedLog)]);

    [$status, $output] = runCpxCommand(['laravel/pint']);

    expect($status)->toBe(0)
        ->and($output)->toContain('from laravel/pint')
        ->and(file_exists($isolatedLog))->toBeTrue();
});

test('it resolves a multi-bin local package by the first forwarded token', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'vendor/package', ['foo', 'bar']);
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';
    $this->writeLocalBinary($root, 'foo', "#!/usr/bin/env php\n<?php exit(1);\n");
    $this->writeLocalBinary($root, 'bar', argvLoggingBinary($logFile));

    [$status] = runCpxCommand(['vendor/package', 'bar', '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag']);
});

test('a leading --remote flag skips the local bin and uses the isolated cache', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $localLog = $this->temporaryDirectory('cpx-log').'/local.json';
    $this->writeLocalBinary($root, 'pint', argvLoggingBinary($localLog));

    $isolatedLog = $this->temporaryDirectory('cpx-log').'/isolated.json';
    prepareCachedPackage('laravel/pint', ['pint'], ['pint' => argvLoggingBinary($isolatedLog)]);

    [$status, $output] = runCpxCommand(['--remote', 'pint']);

    expect($status)->toBe(0)
        ->and($output)->toContain('from laravel/pint')
        ->and(file_exists($isolatedLog))->toBeTrue()
        ->and(file_exists($localLog))->toBeFalse();
});

test('the --remote flag is consumed by cpx and not forwarded to the binary', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->writeLocalBinary($root, 'pint', "#!/usr/bin/env php\n<?php exit(0);\n");

    $isolatedLog = $this->temporaryDirectory('cpx-log').'/isolated.json';
    prepareCachedPackage('laravel/pint', ['pint'], ['pint' => argvLoggingBinary($isolatedLog)]);

    runCpxCommand(['--remote', 'pint', '--foo']);

    expect(json_decode((string) file_get_contents($isolatedLog), true))->toBe(['--foo']);
});

test('a trailing --remote is not a bypass and is forwarded to the local bin verbatim', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';
    $this->writeLocalBinary($root, 'pint', argvLoggingBinary($logFile));

    [$status, $output] = runCpxCommand(['pint', '--remote']);

    expect($status)->toBe(0)
        ->and($output)->toContain('Running pint from')
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--remote']);
});

test('the --remote flag without a target exits non-zero with an actionable message', function () {
    [$status, $output] = runCpxCommand(['--remote']);

    expect($status)->toBe(1)
        ->and($output)->toContain('A package invocation target must be provided.');
});

test('an alias with no local bin falls back to isolated resolution', function () {
    $this->useIsolatedComposerHome();
    $this->prepareLocalProject();
    $isolatedLog = $this->temporaryDirectory('cpx-log').'/isolated.json';
    prepareCachedPackage('laravel/pint', ['pint'], ['pint' => argvLoggingBinary($isolatedLog)]);

    [$status, $output] = runCpxCommand(['pint']);

    expect($status)->toBe(0)
        ->and($output)->toContain('from laravel/pint')
        ->and(file_exists($isolatedLog))->toBeTrue();
});

test('running outside any composer project falls back to isolated resolution', function () {
    $this->useIsolatedComposerHome();
    $this->useWorkingDirectory($this->temporaryDirectory('cpx-noproject'));
    $isolatedLog = $this->temporaryDirectory('cpx-log').'/isolated.json';
    prepareCachedPackage('laravel/pint', ['pint'], ['pint' => argvLoggingBinary($isolatedLog)]);

    [$status] = runCpxCommand(['pint']);

    expect($status)->toBe(0)
        ->and(file_exists($isolatedLog))->toBeTrue();
});

test('a bin-dir entry that is a directory is ignored and cpx falls back', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    mkdir("{$root}/vendor/bin/pint", 0755, true);
    $isolatedLog = $this->temporaryDirectory('cpx-log').'/isolated.json';
    prepareCachedPackage('laravel/pint', ['pint'], ['pint' => argvLoggingBinary($isolatedLog)]);

    [$status] = runCpxCommand(['pint']);

    expect($status)->toBe(0)
        ->and(file_exists($isolatedLog))->toBeTrue();
});
