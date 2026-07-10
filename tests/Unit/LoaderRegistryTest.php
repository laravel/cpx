<?php

use Cpx\Runtime\Context;
use Cpx\Runtime\GenericLoader;
use Cpx\Runtime\LoaderRegistry;

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
