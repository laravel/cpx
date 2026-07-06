<?php

use Cpx\Cache\Metadata;
use Cpx\Packages\Package;
use Symfony\Component\Console\Output\BufferedOutput;

test('a failed install leaves no final dir, no staging residue, and records nothing', function () {
    $this->useIsolatedComposerHome();

    $calls = [];
    fakeComposer($calls, exitCode: 1);

    $package = Package::parse('laravel/pint');

    expect(fn () => $package->installOrUpdatePackage(new BufferedOutput))->toThrow(Exception::class);

    expect(is_dir($package->installPath()))->toBeFalse()
        ->and(glob(cpx_path('laravel/pint/*.installing.*')) ?: [])->toBe([])
        ->and(Metadata::open()->hasPackage('laravel/pint'))->toBeFalse()
        ->and($calls)->toHaveCount(1)
        ->and($calls[0][0])->toBe('require');
});

test('a successful install atomically populates the final dir and records last_updated', function () {
    $this->useIsolatedComposerHome();

    $calls = [];
    fakeComposer($calls);

    $package = Package::parse('laravel/pint');
    $package->installOrUpdatePackage(new BufferedOutput);

    expect(file_exists($package->installPath().'/vendor/autoload.php'))->toBeTrue()
        ->and(glob(cpx_path('laravel/pint/*.installing.*')) ?: [])->toBe([])
        ->and(Metadata::open()->packages['laravel/pint']->lastUpdatedAt)->not->toBeNull();
});

test('an install dir missing the autoloader is treated as incomplete and re-staged', function () {
    $this->useIsolatedComposerHome();

    $package = Package::parse('laravel/pint');

    // Fresh metadata would let a naive "already installed" check skip the broken dir.
    Metadata::transaction(fn (Metadata $metadata) => $metadata->recordUpdate($package));
    mkdir($package->installPath().'/vendor', 0755, true);

    $calls = [];
    fakeComposer($calls);

    $package->installOrUpdatePackage(new BufferedOutput);

    expect(file_exists($package->installPath().'/vendor/autoload.php'))->toBeTrue();
});
