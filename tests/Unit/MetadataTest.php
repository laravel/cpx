<?php

use Cpx\Metadata;
use Cpx\Package;

test('it opens an empty metadata state when no metadata file exists', function () {
    $this->useIsolatedComposerHome();

    $metadata = Metadata::open();

    expect($metadata->packages)->toBe([])
        ->and($metadata->execCache)->toBe([]);
});

test('it saves readable metadata json and round trips package timestamps', function () {
    $this->useIsolatedComposerHome();

    Metadata::open()
        ->updateLastCheckTime(Package::parse('laravel/pint'), 'updated')
        ->updateLastCheckTime(Package::parse('laravel/pint'))
        ->save();

    $metadataFile = cpx_path('.cpx_metadata.json');
    $contents = file_get_contents($metadataFile);
    $metadata = Metadata::open();

    expect($contents)->not->toBeFalse()
        ->and(json_decode((string) $contents, true))->toHaveKey('packages')
        ->and($metadata->hasPackage('laravel/pint'))->toBeTrue()
        ->and($metadata->packages['laravel/pint']->lastUpdatedAt)->not->toBeNull()
        ->and($metadata->packages['laravel/pint']->lastRunAt)->not->toBeNull();
});

test('it handles invalid metadata json as an empty state', function () {
    $this->useIsolatedComposerHome();

    mkdir(dirname(cpx_path('.cpx_metadata.json')), 0755, true);
    file_put_contents(cpx_path('.cpx_metadata.json'), '{invalid');

    $metadata = Metadata::open();

    expect($metadata->packages)->toBe([])
        ->and($metadata->execCache)->toBe([]);
});
