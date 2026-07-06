<?php

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
