<?php

use Cpx\Runtime\Context;
use Cpx\Runtime\GenericLoader;
use Cpx\Runtime\LaravelLoader;
use Cpx\Runtime\LoaderRegistry;
use Cpx\Runtime\SymfonyLoader;

test('laravel projects resolve to the laravel loader via composer.json', function () {
    $root = $this->temporaryDirectory('cpx-laravel');
    file_put_contents("{$root}/composer.json", json_encode([
        'require' => ['laravel/framework' => '^12.0'],
    ], JSON_THROW_ON_ERROR));

    $loader = LoaderRegistry::resolve(new Context($root, $root));

    expect($loader)->toBeInstanceOf(LaravelLoader::class);
});

test('laravel projects resolve to the laravel loader via framework files', function () {
    $root = $this->temporaryDirectory('cpx-laravel');
    mkdir("{$root}/bootstrap", 0755, true);
    file_put_contents("{$root}/artisan", '<?php');
    file_put_contents("{$root}/bootstrap/app.php", '<?php');

    $loader = LoaderRegistry::resolve(new Context($root, $root));

    expect($loader)->toBeInstanceOf(LaravelLoader::class);
});

test('symfony projects resolve to the symfony loader via composer.json', function () {
    $root = $this->temporaryDirectory('cpx-symfony');
    file_put_contents("{$root}/composer.json", json_encode([
        'require' => ['symfony/framework-bundle' => '^7.0'],
    ], JSON_THROW_ON_ERROR));

    $loader = LoaderRegistry::resolve(new Context($root, $root));

    expect($loader)->toBeInstanceOf(SymfonyLoader::class);
});

test('symfony projects resolve to the symfony loader via framework files', function () {
    $root = $this->temporaryDirectory('cpx-symfony');
    mkdir("{$root}/bin", 0755, true);
    mkdir("{$root}/config", 0755, true);
    file_put_contents("{$root}/bin/console", '<?php');
    file_put_contents("{$root}/config/bundles.php", '<?php return [];');

    $loader = LoaderRegistry::resolve(new Context($root, $root));

    expect($loader)->toBeInstanceOf(SymfonyLoader::class);
});

test('plain projects fall back to the generic loader', function () {
    $root = $this->temporaryDirectory('cpx-plain');
    file_put_contents("{$root}/composer.json", json_encode([
        'require' => ['php' => '^8.3'],
    ], JSON_THROW_ON_ERROR));

    $loader = LoaderRegistry::resolve(new Context($root, $root));

    expect($loader)->toBeInstanceOf(GenericLoader::class);
});

test('a missing autoload root falls back to the generic loader', function () {
    $loader = LoaderRegistry::resolve(new Context('/somewhere', null));

    expect($loader)->toBeInstanceOf(GenericLoader::class);
});
