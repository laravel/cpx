<?php

use Cpx\Cache\ExecSandboxMetadata;
use Cpx\Cache\Metadata;
use Cpx\Packages\Package;

test('it opens an empty metadata state when no metadata file exists', function () {
    $this->useIsolatedComposerHome();

    $metadata = Metadata::open();

    expect($metadata->packages)->toBe([])
        ->and($metadata->execCache)->toBe([]);
});

test('it saves readable metadata json and round trips package timestamps', function () {
    $this->useIsolatedComposerHome();

    Metadata::transaction(fn (Metadata $metadata) => $metadata
        ->recordUpdate(Package::parse('laravel/pint'))
        ->recordRun(Package::parse('laravel/pint')));

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

test('a transaction persists and round-trips package timestamps', function () {
    $this->useIsolatedComposerHome();

    Metadata::transaction(fn (Metadata $metadata) => $metadata->recordRun(Package::parse('laravel/pint')));

    $metadata = Metadata::open();

    expect($metadata->hasPackage('laravel/pint'))->toBeTrue()
        ->and($metadata->packages['laravel/pint']->lastRunAt)->not->toBeNull();
});

test('it writes the schema version, round-trips an empty aliases section, and exposes the install path', function () {
    $this->useIsolatedComposerHome();

    Metadata::transaction(fn (Metadata $metadata) => $metadata->recordUpdate(Package::parse('laravel/pint')));

    $decoded = json_decode((string) file_get_contents(cpx_path('.cpx_metadata.json')), true);
    $metadata = Metadata::open();

    expect($decoded['version'])->toBe(2)
        ->and($decoded['aliases'])->toBe([])
        ->and($metadata->aliases)->toBe([])
        ->and($metadata->packages['laravel/pint']->installPath())->toBe(cpx_path('laravel/pint/latest'));
});

test('it loads a legacy metadata file without a version or aliases section', function () {
    $this->useIsolatedComposerHome();

    mkdir(dirname(cpx_path('.cpx_metadata.json')), 0755, true);
    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => ['last_updated' => '2024-01-01 00:00:00', 'last_run' => '2024-01-01 00:00:00'],
        ],
        'execCache' => [
            'sandbox' => ['packages' => ['laravel/pint'], 'last_updated' => 1, 'last_run' => 1],
        ],
    ], JSON_THROW_ON_ERROR));

    $metadata = Metadata::open();

    expect($metadata->packages['laravel/pint']->lastUpdatedAt)->toBe(strtotime('2024-01-01 00:00:00'))
        ->and($metadata->execCache['sandbox'])->toBeInstanceOf(ExecSandboxMetadata::class)
        ->and($metadata->execCache['sandbox']->lastRunAt)->toBe(1)
        ->and($metadata->aliases)->toBe([]);
});

test('interleaved transactions keep every update and leave valid json', function () {
    $this->useIsolatedComposerHome();

    Metadata::transaction(fn (Metadata $metadata) => $metadata->recordRun(Package::parse('laravel/pint')));
    Metadata::transaction(fn (Metadata $metadata) => $metadata->recordUpdate(Package::parse('phpstan/phpstan')));

    $decoded = json_decode((string) file_get_contents(cpx_path('.cpx_metadata.json')), true);
    $metadata = Metadata::open();

    expect($decoded)->toBeArray()
        ->and($metadata->hasPackage('laravel/pint'))->toBeTrue()
        ->and($metadata->hasPackage('phpstan/phpstan'))->toBeTrue()
        ->and($metadata->packages['laravel/pint']->lastRunAt)->not->toBeNull()
        ->and($metadata->packages['phpstan/phpstan']->lastUpdatedAt)->not->toBeNull();
});
