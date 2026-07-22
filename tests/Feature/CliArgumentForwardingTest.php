<?php

test('package arguments are forwarded exactly as argv tokens', function () {
    $this->useIsolatedComposerHome();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    prepareCachedPackage('vendor/package', ['package'], [
        'package' => argvLoggingBinary($logFile),
    ]);

    [$status] = runCpxCommand(['vendor/package', '--name=two words', '--', '--literal', '-x']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe([
            '--name=two words',
            '--',
            '--literal',
            '-x',
        ]);
});

test('short flags, long flags, repeated options, and option values are preserved', function () {
    $this->useIsolatedComposerHome();
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    prepareCachedPackage('vendor/package', ['package'], [
        'package' => argvLoggingBinary($logFile),
    ]);

    [$status] = runCpxCommand(['vendor/package', '-x', '--flag', '--filter=one', '--filter=two', 'value with spaces', 'semi;colon', 'pipe|value', '$(touch injected)']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe([
            '-x',
            '--flag',
            '--filter=one',
            '--filter=two',
            'value with spaces',
            'semi;colon',
            'pipe|value',
            '$(touch injected)',
        ])
        ->and(file_exists(dirname($logFile).'/injected'))->toBeFalse();
});

test('a target binary exit code becomes the cpx exit code', function () {
    $this->useIsolatedComposerHome();

    prepareCachedPackage('vendor/package', ['package'], [
        'package' => noopBinary(23),
    ]);

    [$status] = runCpxCommand(['vendor/package']);

    expect($status)->toBe(23);
});

test('a missing target binary returns a non-zero status with an actionable error', function () {
    $this->useIsolatedComposerHome();

    prepareCachedPackage('vendor/package', ['missing']);

    [$status, $output] = runCpxCommand(['vendor/package']);

    expect($status)->toBe(1)
        ->and($output)->toContain('Command missing not found in vendor/package.');
});
