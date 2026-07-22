<?php

use Cpx\Packages\Package;
use Cpx\Packages\UserAliases;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

test('a package with one binary runs without requiring a binary name', function () {
    $this->useIsolatedComposerHome();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    prepareCachedPackage('vendor/package', ['package'], [
        'package' => argvLoggingBinary($logFile),
    ]);

    [$status] = runCpxCommand(['vendor/package', '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag']);
});

test('a package with multiple binaries uses the first forwarded argument as the binary name', function () {
    $this->useIsolatedComposerHome();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    prepareCachedPackage('vendor/package', ['foo', 'bar'], [
        'foo' => noopBinary(99),
        'bar' => argvLoggingBinary($logFile),
    ]);

    [$status] = runCpxCommand(['vendor/package', 'bar', '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag']);
});

test('a package with multiple binaries keeps a positional argument when a binary matches the package name', function () {
    $this->useIsolatedComposerHome();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    prepareCachedPackage('vendor/package', ['package', 'other'], [
        'package' => argvLoggingBinary($logFile),
        'other' => noopBinary(99),
    ]);

    [$status] = runCpxCommand(['vendor/package', 'tests/Sub']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['tests/Sub']);
});

test('ambiguous multiple-binary packages prompt for the binary to run', function () {
    $this->useIsolatedComposerHome();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    prepareCachedPackage('vendor/package', ['foo', 'bar'], [
        'foo' => noopBinary(99),
        'bar' => argvLoggingBinary($logFile),
    ]);

    Prompt::fake([Key::DOWN, Key::ENTER]);

    [$status, $output] = runCpxCommand(['vendor/package', '--flag']);

    expect($status)->toBe(0)
        ->and($output)->toContain('Which command would you like to run from vendor/package?')
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag']);
})->skipOnWindows();

test('a positional token spelling a package-named binary is forwarded, not consumed', function () {
    $this->useIsolatedComposerHome();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    prepareCachedPackage('vendor/pkg', ['pkg', 'other'], [
        'pkg' => argvLoggingBinary($logFile),
        'other' => noopBinary(99),
    ]);

    [$status] = runCpxCommand(['vendor/pkg', 'pkg', 'tests/Sub']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['pkg', 'tests/Sub']);
});

test('ambiguity errors share wording for cached and local packages', function () {
    $this->useIsolatedComposerHome();

    prepareCachedPackage('vendor/package', ['foo', 'bar'], [
        'foo' => noopBinary(),
        'bar' => noopBinary(),
    ]);
    $root = $this->prepareLocalPackage(['bin/foo', 'bin/bar'], 'vendor/local');

    [, $cachedOutput] = runCpxCommand(['vendor/package']);
    [, $localOutput] = runCpxCommand([$root]);

    expect($cachedOutput)->toContain('More than 1 bin command found for vendor/package: foo, bar.')
        ->and($localOutput)->toContain("More than 1 bin command found for {$root}: foo, bar.");
});

test('missing binary errors share wording for cached and local packages', function () {
    $this->useIsolatedComposerHome();

    prepareCachedPackage('vendor/ghost', ['ghost']);
    $root = $this->prepareLocalPackage(['bin/missing'], 'vendor/local');

    [, $cachedOutput] = runCpxCommand(['vendor/ghost']);
    [, $localOutput] = runCpxCommand([$root]);

    expect($cachedOutput)->toContain('Command ghost not found in vendor/ghost.')
        ->and($localOutput)->toContain("Command missing not found in {$root}.");
});

test('ambiguous multiple-binary packages list the available binaries when the terminal cannot prompt', function () {
    $this->useIsolatedComposerHome();

    prepareCachedPackage('vendor/package', ['foo', 'bar'], [
        'foo' => noopBinary(),
        'bar' => noopBinary(),
    ]);

    [$status, $output] = runCpxCommand(['vendor/package']);

    expect($status)->toBe(1)
        ->and($output)->toContain('More than 1 bin command found for vendor/package: foo, bar.');
});

test('an aliased multiple-binary package runs its pinned binary and forwards all arguments', function () {
    $this->useIsolatedComposerHome();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    prepareCachedPackage('vendor/package', ['foo', 'bar'], [
        'foo' => noopBinary(99),
        'bar' => argvLoggingBinary($logFile),
    ]);

    UserAliases::open()
        ->put('tool', Package::parse('vendor/package')->withBin('bar'))
        ->save();

    // The leading positional token must reach the pinned binary rather than being
    // consumed as a binary selector the way an unpinned multi-binary package would.
    [$status] = runCpxCommand(['tool', 'foo', '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['foo', '--flag']);
});

test('an aliased package with a stale pinned binary fails with a clear error', function () {
    $this->useIsolatedComposerHome();

    prepareCachedPackage('vendor/package', ['foo'], [
        'foo' => noopBinary(),
    ]);

    UserAliases::open()
        ->put('tool', Package::parse('vendor/package')->withBin('removed'))
        ->save();

    [$status, $output] = runCpxCommand(['tool']);

    expect($status)->toBe(1)
        ->and($output)->toContain("'removed' was not found");
});
