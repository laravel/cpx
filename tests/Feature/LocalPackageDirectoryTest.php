<?php

use Cpx\Packages\BinExecutable;

test('an aliased local package directory runs from its saved source without invoking composer', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalPackage('bin/tool', 'vendor/tool');
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    $this->writeLocalPackageBinary($root, 'bin/tool', argvLoggingBinary($logFile, 23));

    $calls = [];
    fakeComposer($calls);

    [$aliasStatus] = runCpxCommand(['alias', $root, 'local-tool']);
    [$runStatus, $output] = runCpxCommand(['local-tool', '--flag']);

    expect($aliasStatus)->toBe(0)
        ->and($runStatus)->toBe(23)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag'])
        ->and($calls)->toBe([])
        ->and($output)->toContain("Running tool from {$root}");
});

test('an aliased local multi-bin package runs its pinned binary', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalPackage(['bin/foo', 'bin/bar'], 'vendor/package');
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    $this->writeLocalPackageBinary($root, 'bin/foo', noopBinary(99));
    $this->writeLocalPackageBinary($root, 'bin/bar', argvLoggingBinary($logFile));

    [$aliasStatus] = runCpxCommand(['alias', $root, 'tool', '--bin=bar']);
    [$runStatus] = runCpxCommand(['tool', 'foo', '--flag']);

    expect($aliasStatus)->toBe(0)
        ->and($runStatus)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['foo', '--flag']);
});

test('an absolute local package directory runs without invoking composer', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalPackage('bin/tool', 'vendor/tool');
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    $this->writeLocalPackageBinary($root, 'bin/tool', argvLoggingBinary($logFile, 23));

    $calls = [];
    fakeComposer($calls);

    [$status, $output] = runCpxCommand([$root, '--name=two words', '--', '--literal']);

    expect($status)->toBe(23)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe([
            '--name=two words',
            '--',
            '--literal',
        ])
        ->and($calls)->toBe([])
        ->and(file_exists(cpx_path('.cpx_metadata.json')))->toBeFalse()
        ->and($output)->toContain("Running tool from {$root}");
});

test('a parent-relative local package directory resolves from the working directory', function () {
    $workspace = $this->temporaryDirectory('cpx-workspace');
    $root = $this->prepareLocalPackage(['bin/tool'], 'vendor/tool', "{$workspace}/tool");
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';
    $project = "{$workspace}/project";

    mkdir($project, 0755, true);
    $this->writeLocalPackageBinary($root, 'bin/tool', argvLoggingBinary($logFile));
    $this->useWorkingDirectory($project);

    [$status] = runCpxCommand(['../tool', '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag']);
});

test('a current-relative local package directory resolves from the working directory', function () {
    $workspace = $this->temporaryDirectory('cpx-workspace');
    $root = $this->prepareLocalPackage(['bin/tool'], 'vendor/tool', "{$workspace}/packages/tool");
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    $this->writeLocalPackageBinary($root, 'bin/tool', argvLoggingBinary($logFile));
    $this->useWorkingDirectory($workspace);

    [$status] = runCpxCommand(['./packages/tool', '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag']);
});

test('a literal home-relative local package directory is expanded', function () {
    $composerHome = $this->useIsolatedComposerHome();
    $home = dirname($composerHome);
    $root = $this->prepareLocalPackage(['bin/tool'], 'vendor/tool', "{$home}/tools/local-package");
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    $this->writeLocalPackageBinary($root, 'bin/tool', argvLoggingBinary($logFile));

    [$status] = runCpxCommand(['~/tools/local-package', '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag']);
});

test('a local package path containing spaces is preserved', function () {
    $workspace = $this->temporaryDirectory('cpx-workspace');
    $root = $this->prepareLocalPackage(['bin/tool'], 'vendor/tool', "{$workspace}/local package");
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    $this->writeLocalPackageBinary($root, 'bin/tool', argvLoggingBinary($logFile));

    [$status] = runCpxCommand([$root, 'value with spaces']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['value with spaces']);
});

test('a symlinked local package directory runs from its canonical directory', function () {
    $workspace = $this->temporaryDirectory('cpx-workspace');
    $root = $this->prepareLocalPackage(['bin/tool'], 'vendor/tool', "{$workspace}/source");
    $link = "{$workspace}/linked-package";
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    $this->writeLocalPackageBinary($root, 'bin/tool', argvLoggingBinary($logFile));
    symlink($root, $link);

    [$status, $output] = runCpxCommand([$link]);

    unlink($link);

    expect($status)->toBe(0)
        ->and($output)->toContain("Running tool from {$root}");
})->skip(! canCreateSymlinks(), 'symlink creation is unavailable (Windows without Developer Mode)');

test('a local package infers a binary matching its composer package name', function () {
    $root = $this->prepareLocalPackage(['bin/tool', 'bin/other'], 'vendor/tool');
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    $this->writeLocalPackageBinary($root, 'bin/tool', argvLoggingBinary($logFile));
    $this->writeLocalPackageBinary($root, 'bin/other', noopBinary(99));

    [$status] = runCpxCommand([$root, '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag']);
});

test('a local package uses its directory name to infer a binary when composer has no name', function () {
    $workspace = $this->temporaryDirectory('cpx-workspace');
    $root = $this->prepareLocalPackage(['bin/tool', 'bin/other'], null, "{$workspace}/tool");
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    $this->writeLocalPackageBinary($root, 'bin/tool', argvLoggingBinary($logFile));
    $this->writeLocalPackageBinary($root, 'bin/other', noopBinary(99));

    [$status] = runCpxCommand([$root, '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag']);
});

test('the first forwarded token selects a binary from a local multi-bin package', function () {
    $root = $this->prepareLocalPackage(['bin/foo', 'bin/bar'], 'vendor/package');
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    $this->writeLocalPackageBinary($root, 'bin/foo', noopBinary(99));
    $this->writeLocalPackageBinary($root, 'bin/bar', argvLoggingBinary($logFile));

    [$status] = runCpxCommand([$root, 'bar', '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag']);
});

test('an ambiguous local multi-bin package lists its binaries', function () {
    $root = $this->prepareLocalPackage(['bin/foo', 'bin/bar'], 'vendor/package');

    $this->writeLocalPackageBinary($root, 'bin/foo', noopBinary());
    $this->writeLocalPackageBinary($root, 'bin/bar', noopBinary());

    [$status, $output] = runCpxCommand([$root]);

    expect($status)->toBe(1)
        ->and($output)->toContain("More than 1 bin command found for {$root}: foo, bar.");
});

test('a local package must declare a binary', function () {
    $root = $this->prepareLocalPackage([], 'vendor/package');

    [$status, $output] = runCpxCommand([$root]);

    expect($status)->toBe(1)
        ->and($output)->toContain("No bin command found in {$root}.");
});

test('a local package declared binary must exist', function () {
    $root = $this->prepareLocalPackage(['bin/missing'], 'vendor/package');

    [$status, $output] = runCpxCommand([$root]);

    expect($status)->toBe(1)
        ->and($output)->toContain("Command missing not found in {$root}.");
});

test('a local package directory must exist', function () {
    $path = $this->temporaryDirectory('cpx-missing').'/does-not-exist';

    [$status, $output] = runCpxCommand([$path]);

    expect($status)->toBe(1)
        ->and($output)->toContain("Local package directory '{$path}' does not exist.");
});

test('a local package directory must contain composer json', function () {
    $root = $this->temporaryDirectory('cpx-local-package');

    [$status, $output] = runCpxCommand([$root]);

    expect($status)->toBe(1)
        ->and($output)->toContain("No composer.json file found in local package directory {$root}.");
});

test('a local package composer json must be valid', function () {
    $root = $this->temporaryDirectory('cpx-local-package');
    file_put_contents("{$root}/composer.json", 'not-json{');

    [$status, $output] = runCpxCommand([$root]);

    expect($status)->toBe(1)
        ->and($output)->toContain("The composer.json file in {$root} is not valid JSON.");
});

test('a local package composer json must contain an object', function () {
    $root = $this->temporaryDirectory('cpx-local-package');
    file_put_contents("{$root}/composer.json", '[]');

    [$status, $output] = runCpxCommand([$root]);

    expect($status)->toBe(1)
        ->and($output)->toContain("The composer.json file in {$root} must contain a JSON object.");
});

test('a local package must have installed dependencies', function () {
    $root = $this->temporaryDirectory('cpx-local-package');
    file_put_contents("{$root}/composer.json", json_encode([
        'name' => 'vendor/package',
        'bin' => ['bin/package'],
    ], JSON_THROW_ON_ERROR));

    [$status, $output] = runCpxCommand([$root]);

    expect($status)->toBe(1)
        ->and($output)->toContain("Dependencies are not installed for the local package at {$root}.")
        ->and($output)->toContain('composer install');
});

test('skip local does not override an explicit local package directory', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalPackage(['bin/tool'], 'vendor/tool');
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';

    $this->writeLocalPackageBinary($root, 'bin/tool', argvLoggingBinary($logFile));

    $calls = [];
    fakeComposer($calls);

    [$status] = runCpxCommand(['--skip-local', $root, '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag'])
        ->and($calls)->toBe([]);
});

test('local package php binaries use the cross-platform binary executable resolution', function () {
    $root = $this->prepareLocalPackage(['bin/tool'], 'vendor/tool');
    $logFile = $this->temporaryDirectory('cpx-log').'/argv.json';
    $this->writeLocalPackageBinary($root, 'bin/tool', argvLoggingBinary($logFile));

    BinExecutable::fakeWindows();

    [$status] = runCpxCommand([$root, '--flag']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe(['--flag']);
});
