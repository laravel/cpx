<?php

use Cpx\Cache\Metadata;
use Cpx\Composer\ComposerRunner;
use Cpx\Exceptions\ComposerInstallException;
use Cpx\Packages\ExecSandbox;
use Laravel\Prompts\Output\BufferedConsoleOutput;
use Laravel\Prompts\Prompt;

test('a staged sandbox install lands the final directory and removes the staging directory', function () {
    $this->useIsolatedComposerHome();

    $calls = [];
    fakeComposer($calls);

    $path = ExecSandbox::forPackages(['laravel/pint'])->ensureInstalled();

    expect(file_exists("{$path}/vendor/autoload.php"))->toBeTrue()
        ->and(glob(cpx_path('.exec_cache/*.installing.*')) ?: [])->toBe([]);
});

test('a failing staged sandbox install cleans up staging and raises the component exception', function () {
    $this->useIsolatedComposerHome();

    $calls = [];
    fakeComposer($calls, exitCode: 1);

    $sandbox = ExecSandbox::forPackages(['laravel/pint']);

    expect(fn () => $sandbox->ensureInstalled())
        ->toThrow(ComposerInstallException::class, 'Failed to install package: laravel/pint.');

    expect(is_dir($sandbox->path()))->toBeFalse()
        ->and(glob(cpx_path('.exec_cache/*.installing.*')) ?: [])->toBe([]);
});

test('a failed sandbox update warns and keeps the existing install working', function () {
    $this->useIsolatedComposerHome();

    $calls = [];
    fakeComposer($calls);

    $sandbox = ExecSandbox::forPackages(['laravel/pint']);
    $sandbox->ensureInstalled();

    Metadata::transaction(function (Metadata $metadata) use ($sandbox): void {
        $metadata->execCache[$sandbox->key]->lastUpdatedAt = time() - 2 * Metadata::UPDATE_CHECK_INTERVAL;
    });

    fakeComposer($calls, exitCode: 1);
    Prompt::setOutput($buffer = new BufferedConsoleOutput);

    $path = $sandbox->ensureInstalled();

    expect($path)->toBe($sandbox->path())
        ->and($buffer->fetch())->toContain('Could not update the exec sandbox');
});

test('an unexpected non-composer failure during a sandbox update propagates', function () {
    $this->useIsolatedComposerHome();

    $calls = [];
    fakeComposer($calls);

    $sandbox = ExecSandbox::forPackages(['laravel/pint']);
    $sandbox->ensureInstalled();

    Metadata::transaction(function (Metadata $metadata) use ($sandbox): void {
        $metadata->execCache[$sandbox->key]->lastUpdatedAt = time() - 2 * Metadata::UPDATE_CHECK_INTERVAL;
    });

    ComposerRunner::fake(function (): int {
        throw new LogicException('boom');
    });

    expect(fn () => $sandbox->ensureInstalled())->toThrow(LogicException::class, 'boom');
});
