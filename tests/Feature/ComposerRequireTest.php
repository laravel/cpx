<?php

use Cpx\Cache\ExecSandboxMetadata;
use Cpx\Cache\Metadata;
use Cpx\Runtime\ComposerRequire;

test('composer_require installs into a safe sandbox key and records a typed exec entry', function () {
    $this->useIsolatedComposerHome();

    $calls = [];
    fakeComposer($calls);

    composer_require('laravel/pint');

    $key = hash('sha256', 'laravel/pint');
    $sandbox = Metadata::open()->execCache[$key];

    expect(is_dir(cpx_path(".exec_cache/{$key}")))->toBeTrue()
        ->and($sandbox)->toBeInstanceOf(ExecSandboxMetadata::class)
        ->and($sandbox->key)->toBe($key)
        ->and($sandbox->packages)->toBe(['laravel/pint'])
        ->and($sandbox->lastRunAt)->not->toBeNull()
        ->and(file_exists(cpx_path(".exec_cache/{$key}/vendor/autoload.php")))->toBeTrue()
        ->and($calls)->toHaveCount(1)
        ->and($calls[0][0])->toBe('require')
        ->and($calls[0][1])->toBe('laravel/pint');
});

test('the hidden sandbox command installs the packages and prints the sandbox path', function () {
    $this->useIsolatedComposerHome();

    $calls = [];
    fakeComposer($calls);

    [$status, $output] = runCpxCommand([ComposerRequire::COMMAND, 'laravel/pint']);

    $key = hash('sha256', 'laravel/pint');

    expect($status)->toBe(0)
        ->and(trim($output))->toBe(cpx_path(".exec_cache/{$key}"))
        ->and($calls)->toHaveCount(1)
        ->and($calls[0][0])->toBe('require')
        ->and($calls[0][1])->toBe('laravel/pint');
});
