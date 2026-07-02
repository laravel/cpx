<?php

declare(strict_types=1);

use Cpx\Composer\ComposerRunner;

test('it assembles arguments with no-interaction and working-dir and returns the runner exit code', function () {
    $captured = null;
    ComposerRunner::fake(function (array $command) use (&$captured): int {
        $captured = $command;

        return 0;
    });

    $exitCode = ComposerRunner::run(['require', 'vendor/package:^1@dev', '--no-progress'], '/tmp/example dir');

    expect($exitCode)->toBe(0)
        ->and($captured)->toBe([
            'require',
            'vendor/package:^1@dev',
            '--no-progress',
            '--no-interaction',
            '--working-dir=/tmp/example dir',
        ]);
});

test('it omits the working-dir option when no directory is given', function () {
    $captured = null;
    ComposerRunner::fake(function (array $command) use (&$captured): int {
        $captured = $command;

        return 0;
    });

    ComposerRunner::run(['update']);

    expect($captured)->toBe(['update', '--no-interaction']);
});

test('it throws a uniform message when the runner reports a failure', function () {
    ComposerRunner::fake(fn (array $command): int => 12);

    ComposerRunner::run(['update']);
})->throws(Exception::class, 'Composer command failed: update');

test('it runs an offline composer command in-process and returns success', function () {
    $this->useIsolatedComposerHome();

    expect(ComposerRunner::run(['about', '--quiet']))->toBe(0);
});

test('it throws the uniform message when an unknown composer command fails in-process', function () {
    $this->useIsolatedComposerHome();

    ComposerRunner::run(['this-command-does-not-exist', '--quiet']);
})->throws(Exception::class, 'Composer command failed: this-command-does-not-exist');

test('it installs a package in-process from a local path repository without network', function () {
    $this->useIsolatedComposerHome();

    $staging = $this->stagingWithPathPackages(['cpx-fixture/pkg']);

    $exitCode = ComposerRunner::run(['require', 'cpx-fixture/pkg:*', '--quiet'], $staging);

    expect($exitCode)->toBe(0)
        ->and(file_exists("{$staging}/vendor/autoload.php"))->toBeTrue()
        ->and(file_exists("{$staging}/vendor/cpx-fixture/pkg/composer.json"))->toBeTrue();
});

test('it restores the working directory even when a working-dir command fails', function () {
    $this->useIsolatedComposerHome();

    $before = getcwd();
    $staging = $this->temporaryDirectory('cpx-cwd');

    expect(fn () => ComposerRunner::run(['this-command-does-not-exist', '--quiet'], $staging))
        ->toThrow(Exception::class);

    expect(getcwd())->toBe($before);
});

test('it installs multiple packages sequentially in the same process', function () {
    $this->useIsolatedComposerHome();

    $staging = $this->stagingWithPathPackages(['cpx-fixture/one', 'cpx-fixture/two']);

    ComposerRunner::run(['require', 'cpx-fixture/one:*', '--quiet'], $staging);
    ComposerRunner::run(['require', 'cpx-fixture/two:*', '--quiet'], $staging);

    expect(file_exists("{$staging}/vendor/cpx-fixture/one/composer.json"))->toBeTrue()
        ->and(file_exists("{$staging}/vendor/cpx-fixture/two/composer.json"))->toBeTrue();
});

test('it runs global composer commands in-process against COMPOSER_HOME', function () {
    $composerHome = $this->useIsolatedComposerHome();

    ComposerRunner::run(['global', 'config', 'sort-packages', 'true', '--quiet']);

    expect(file_exists("{$composerHome}/composer.json"))->toBeTrue()
        ->and(json_decode((string) file_get_contents("{$composerHome}/composer.json"), true))
        ->toHaveKey('config.sort-packages');
});
