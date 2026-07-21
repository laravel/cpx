<?php

use Cpx\Exceptions\MalformedAliasesException;
use Cpx\Packages\Package;
use Cpx\Packages\UserAliases;

test('it opens an empty alias state when no aliases file exists', function () {
    $this->useIsolatedComposerHome();

    expect(UserAliases::open()->all())->toBe([]);
});

test('it saves and reloads a user alias', function () {
    $this->useIsolatedComposerHome();

    UserAliases::open()->put('mypint', Package::parse('laravel/pint'))->save();

    $aliasesFile = cpx_path('aliases.json');
    $contents = file_get_contents($aliasesFile);
    $aliases = UserAliases::open();

    expect($contents)->not->toBeFalse()
        ->and(json_decode((string) $contents, true))->toBe(['mypint' => ['package' => 'laravel/pint', 'bin' => null]])
        ->and($aliases->has('mypint'))->toBeTrue()
        ->and($aliases->find('mypint')?->fullPackageString())->toBe('laravel/pint');
});

test('it overwrites an existing alias of the same name', function () {
    $this->useIsolatedComposerHome();

    UserAliases::open()->put('tool', Package::parse('vendor/one'))->save();
    UserAliases::open()->put('tool', Package::parse('vendor/two'))->save();

    expect(UserAliases::open()->find('tool')?->fullPackageString())->toBe('vendor/two');
});

test('find returns null for an unknown alias', function () {
    $this->useIsolatedComposerHome();

    expect(UserAliases::open()->find('missing'))->toBeNull();
});

test('it removes a saved alias', function () {
    $this->useIsolatedComposerHome();

    UserAliases::open()->put('mypint', Package::parse('laravel/pint'))->save();
    UserAliases::open()->remove('mypint')->save();

    $aliases = UserAliases::open();

    expect($aliases->has('mypint'))->toBeFalse()
        ->and(json_decode((string) file_get_contents(cpx_path('aliases.json')), true))->toBe([]);
});

test('removing an unknown alias is a no-op', function () {
    $this->useIsolatedComposerHome();

    UserAliases::open()->put('mypint', Package::parse('laravel/pint'))->save();
    UserAliases::open()->remove('missing')->save();

    expect(UserAliases::open()->has('mypint'))->toBeTrue();
});

test('it skips aliases whose package fails to parse and keeps healthy ones', function () {
    $this->useIsolatedComposerHome();

    writeAliasesFile([
        'good' => ['package' => 'laravel/pint', 'bin' => null],
        'broken' => ['package' => 'Not A Package!!', 'bin' => null],
    ]);

    $aliases = UserAliases::open();

    expect($aliases->find('good')?->fullPackageString())->toBe('laravel/pint')
        ->and($aliases->find('broken'))->toBeNull()
        ->and($aliases->all())->not->toHaveKey('broken');
});

test('it skips local-path aliases whose directory no longer exists', function () {
    $this->useIsolatedComposerHome();

    $root = $this->temporaryDirectory('cpx-stale-alias');
    rmdir($root);

    writeAliasesFile([
        'good' => ['package' => 'laravel/pint', 'bin' => null],
        'stale' => ['package' => $root, 'bin' => null],
    ]);

    $aliases = UserAliases::open();

    expect($aliases->find('good')?->fullPackageString())->toBe('laravel/pint')
        ->and($aliases->find('stale'))->toBeNull();
});

test('it skips malformed alias entries without throwing', function () {
    $this->useIsolatedComposerHome();

    writeAliasesFile([
        'good' => ['package' => 'laravel/pint', 'bin' => null],
        'notAnArray' => 'laravel/pint',
        'badBin' => ['package' => 'laravel/pint', 'bin' => 123],
        'noPackage' => ['bin' => 'tool'],
    ]);

    $aliases = UserAliases::open();

    expect(array_keys($aliases->all()))->toBe(['good'])
        ->and($aliases->find('good')?->fullPackageString())->toBe('laravel/pint');
});

test('broken aliases stay addressable and survive saves until removed', function () {
    $this->useIsolatedComposerHome();

    writeAliasesFile([
        'good' => ['package' => 'laravel/pint', 'bin' => null],
        'broken' => ['package' => 'Not A Package!!', 'bin' => null],
    ]);

    $aliases = UserAliases::open();
    $aliases->save();

    expect($aliases->has('broken'))->toBeTrue()
        ->and(json_decode((string) file_get_contents(cpx_path('aliases.json')), true))
        ->toHaveKey('broken');

    UserAliases::open()->remove('broken')->save();
    $healed = json_decode((string) file_get_contents(cpx_path('aliases.json')), true);

    expect($healed)->not->toHaveKey('broken')
        ->and($healed)->toHaveKey('good');
});

test('re-aliasing over a broken name replaces the broken entry', function () {
    $this->useIsolatedComposerHome();

    writeAliasesFile([
        'broken' => ['package' => 'Not A Package!!', 'bin' => null],
    ]);

    UserAliases::open()->put('broken', Package::parse('laravel/pint'))->save();

    expect(UserAliases::open()->find('broken')?->fullPackageString())->toBe('laravel/pint');
});

test('a fully malformed aliases file still throws', function () {
    $this->useIsolatedComposerHome();

    mkdir(dirname(cpx_path('aliases.json')), 0755, true);
    file_put_contents(cpx_path('aliases.json'), '"just a string"');

    UserAliases::open();
})->throws(MalformedAliasesException::class);
