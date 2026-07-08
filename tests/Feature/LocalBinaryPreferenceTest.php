<?php

use Cpx\Packages\Package;
use Cpx\Packages\UserAliases;

test('a local project binary runs before any isolated install', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    $this->installLocalPackage($root, 'laravel/pint', ['pint']);
    $this->writeLocalBinary($root, 'pint', argvLoggingBinary($logFile));

    $calls = [];
    fakeComposer($calls);

    [$status, $output] = runCpxCommand(['laravel/pint', '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag'])
        ->and($calls)->toBe([])
        ->and($output)->toContain('Running pint from');
});

test('a custom composer bin-dir is honoured for local resolution', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject('tools');
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    $this->installLocalPackage($root, 'laravel/pint', ['pint']);
    $this->writeLocalBinary($root, 'pint', argvLoggingBinary($logFile), 'tools');

    $calls = [];
    fakeComposer($calls);

    [$status] = runCpxCommand(['laravel/pint']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe([])
        ->and($calls)->toBe([]);
});

test('local binary execution preserves forwarded arguments and propagates the exit code', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    $this->installLocalPackage($root, 'laravel/pint', ['pint']);
    $this->writeLocalBinary($root, 'pint', argvLoggingBinary($logFile, 23));

    $calls = [];
    fakeComposer($calls);

    [$status] = runCpxCommand(['laravel/pint', 'fix', '--dirty']);

    expect($status)->toBe(23)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['fix', '--dirty']);
});

test('a bare command name resolves to the local project bin-dir', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    $this->writeLocalBinary($root, 'phpunit', argvLoggingBinary($logFile));

    $calls = [];
    fakeComposer($calls);

    [$status] = runCpxCommand(['phpunit', '--filter=Foo']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--filter=Foo']);
});

test('a user alias runs the local binary when its package is installed locally', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    $this->installLocalPackage($root, 'laravel/pint', ['pint']);
    $this->writeLocalBinary($root, 'pint', argvLoggingBinary($logFile));

    UserAliases::open()->put('pint', Package::parse('laravel/pint'))->save();

    $calls = [];
    fakeComposer($calls);

    [$status] = runCpxCommand(['pint', '--test']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--test']);
});

test('resolution falls back to the isolated install when no local binary exists', function () {
    $this->useIsolatedComposerHome();
    $this->prepareLocalProject();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    prepareCachedPackage('vendor/package', ['package'], [
        'package' => argvLoggingBinary($logFile),
    ]);

    [$status] = runCpxCommand(['vendor/package', '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag']);
});

test('a bare command with no local binary is unrecognised', function () {
    $this->useIsolatedComposerHome();
    $this->prepareLocalProject();

    [$status, $output] = runCpxCommand(['unknown-tool']);

    expect($status)->toBe(1)
        ->and($output)->toContain('Unrecognised command unknown-tool');
});

test('the --skip-local flag skips the local binary and uses the isolated install', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    // The local pint would exit 77 if it were (incorrectly) preferred over --skip-local.
    $this->installLocalPackage($root, 'laravel/pint', ['pint']);
    $this->writeLocalBinary($root, 'pint', noopBinary(77));

    prepareCachedPackage('laravel/pint', ['pint'], [
        'pint' => argvLoggingBinary($logFile),
    ]);

    [$status] = runCpxCommand(['--skip-local', 'laravel/pint', '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag']);
});
