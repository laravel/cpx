<?php

declare(strict_types=1);

use Cpx\Composer\ComposerRunner;

test('it intercepts the re-invocation token and returns composer\'s exit code', function () {
    $this->useIsolatedComposerHome();

    [$status] = runCpxCommand([ComposerRunner::REINVOKE_TOKEN, 'about', '--quiet']);

    expect($status)->toBe(0);
});

test('it returns a non-zero exit code when the re-invoked composer command fails', function () {
    $this->useIsolatedComposerHome();

    [$status] = runCpxCommand([ComposerRunner::REINVOKE_TOKEN, 'this-command-does-not-exist', '--quiet']);

    expect($status)->toBe(1);
});

test('it forwards options, arguments, and a -- separator to composer verbatim', function () {
    $this->useIsolatedComposerHome();

    $staging = $this->temporaryDirectory('cpx-forward');
    file_put_contents("{$staging}/composer.json", json_encode(['name' => 'cpx-fixture/forward'], JSON_THROW_ON_ERROR));

    [$status] = runCpxCommand([
        ComposerRunner::REINVOKE_TOKEN,
        'config',
        "--working-dir={$staging}",
        '--no-interaction',
        '--',
        'sort-packages',
        'true',
    ]);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents("{$staging}/composer.json"), true))
        ->toHaveKey('config.sort-packages');
});
