<?php

use Cpx\Cache\ExecSandboxMetadata;
use Cpx\Cache\Metadata;

test('composer_require installs into a safe sandbox key and records a typed exec entry', function () {
    $this->useIsolatedComposerHome();

    $binDirectory = $this->temporaryDirectory('cpx-bin');
    writeExecutable($binDirectory.'/composer', composerAutoloaderStub());
    $this->setEnvironmentVariable('PATH', $binDirectory.PATH_SEPARATOR.getenv('PATH'));

    composer_require('laravel/pint');

    $key = hash('sha256', 'laravel/pint');
    $sandbox = Metadata::open()->execCache[$key];

    expect(is_dir(cpx_path(".exec_cache/{$key}")))->toBeTrue()
        ->and($sandbox)->toBeInstanceOf(ExecSandboxMetadata::class)
        ->and($sandbox->key)->toBe($key)
        ->and($sandbox->packages)->toBe(['laravel/pint'])
        ->and($sandbox->lastRunAt)->not->toBeNull()
        ->and(file_exists(cpx_path(".exec_cache/{$key}/vendor/autoload.php")))->toBeTrue();
});
