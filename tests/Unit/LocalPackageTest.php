<?php

use Cpx\Packages\LocalPackage;

test('it only supports explicit directory path syntax', function (string $target, bool $supported) {
    expect(LocalPackage::supports($target))->toBe($supported);
})->with([
    'posix absolute' => ['/Users/example/package', true],
    'windows drive absolute' => ['C:\\Users\\example\\package', true],
    'windows drive forward slash' => ['C:/Users/example/package', true],
    'windows unc absolute' => ['\\\\server\\packages\\tool', true],
    'current directory' => ['.', true],
    'current relative' => ['./package', true],
    'windows current relative' => ['.\\package', true],
    'parent directory' => ['..', true],
    'parent relative' => ['../package', true],
    'windows parent relative' => ['..\\package', true],
    'home directory' => ['~', true],
    'home relative' => ['~/package', true],
    'windows home relative' => ['~\\package', true],
    'composer package' => ['laravel/pint', false],
    'bare command' => ['pint', false],
    'named user home' => ['~someone/package', false],
]);

test('it exposes the canonical root and composer package short name', function () {
    $root = $this->prepareLocalPackage(['bin/tool'], 'vendor/tool');

    $package = LocalPackage::parse($root.'/../'.basename($root));

    expect($package->root)->toBe($root)
        ->and($package->name)->toBe('tool');
});

test('it filters non-string manifest bin entries', function () {
    $root = $this->prepareLocalPackage(['bin/tool', 123, null]);

    expect(LocalPackage::parse($root)->binaries(''))->toBe(['tool' => 'bin/tool']);
});

test('cache lifecycle members throw for local packages', function () {
    $package = LocalPackage::parse($this->prepareLocalPackage());

    expect(fn () => $package->installPath())->toThrow(BadMethodCallException::class)
        ->and(fn () => $package->delete())->toThrow(BadMethodCallException::class)
        ->and(fn () => $package->isInstalled())->toThrow(BadMethodCallException::class)
        ->and(fn () => $package->shouldCheckForUpdates())->toThrow(BadMethodCallException::class);
});

test('it rejects a non-directory path when resolved directly', function () {
    $root = $this->temporaryDirectory('cpx-local-package');
    $path = "{$root}/package.txt";
    file_put_contents($path, 'contents');

    expect(fn () => LocalPackage::parse($path))
        ->toThrow(InvalidArgumentException::class, "Local package path '{$path}' is not a directory.");
});

test('it fails to expand a home path when no home directory is available', function () {
    foreach (['HOME', 'USERPROFILE', 'HOMEDRIVE', 'HOMEPATH'] as $variable) {
        $this->setEnvironmentVariable($variable, '');
    }

    expect(fn () => LocalPackage::parse('~/package'))
        ->toThrow(InvalidArgumentException::class, 'the home directory could not be determined');
});
