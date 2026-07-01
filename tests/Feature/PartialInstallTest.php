<?php

use Cpx\Cache\Metadata;
use Cpx\Packages\Package;
use Symfony\Component\Console\Output\BufferedOutput;

test('a failed install leaves no final dir, no staging residue, and records nothing', function () {
    $this->useIsolatedComposerHome();

    $binDirectory = $this->temporaryDirectory('cpx-bin');
    writeExecutable($binDirectory.'/composer', "#!/usr/bin/env php\n<?php exit(1);\n");
    $this->setEnvironmentVariable('PATH', $binDirectory.PATH_SEPARATOR.getenv('PATH'));

    $package = Package::parse('laravel/pint');

    expect(fn () => $package->installOrUpdatePackage(new BufferedOutput))->toThrow(Exception::class);

    expect(is_dir($package->installPath()))->toBeFalse()
        ->and(glob(cpx_path('laravel/pint/*.installing.*')) ?: [])->toBe([])
        ->and(Metadata::open()->hasPackage('laravel/pint'))->toBeFalse();
});

test('a successful install atomically populates the final dir and records last_updated', function () {
    $this->useIsolatedComposerHome();

    $binDirectory = $this->temporaryDirectory('cpx-bin');
    writeExecutable($binDirectory.'/composer', composerAutoloaderStub());
    $this->setEnvironmentVariable('PATH', $binDirectory.PATH_SEPARATOR.getenv('PATH'));

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

    $binDirectory = $this->temporaryDirectory('cpx-bin');
    writeExecutable($binDirectory.'/composer', composerAutoloaderStub());
    $this->setEnvironmentVariable('PATH', $binDirectory.PATH_SEPARATOR.getenv('PATH'));

    $package->installOrUpdatePackage(new BufferedOutput);

    expect(file_exists($package->installPath().'/vendor/autoload.php'))->toBeTrue();
});
