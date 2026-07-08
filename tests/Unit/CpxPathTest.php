<?php

test('it stores cpx state under the user home directory', function () {
    $this->useIsolatedComposerHome();
    $home = getenv('HOME');

    expect(cpx_path('metadata.json'))->toBe("{$home}/.cpx/metadata.json");
});

test('it resolves cpx state independently of composer home', function () {
    $home = $this->temporaryDirectory('cpx-home');
    $this->setEnvironmentVariable('HOME', $home);
    $this->setEnvironmentVariable('COMPOSER_HOME', $this->temporaryDirectory('composer-elsewhere'));
    $this->setEnvironmentVariable('CPX_HOME', '');

    expect(cpx_path('metadata.json'))->toBe("{$home}/.cpx/metadata.json");
});

test('it stores cpx state under CPX_HOME when set', function () {
    $cpxHome = $this->temporaryDirectory('cpx-home-override');
    $this->setEnvironmentVariable('CPX_HOME', $cpxHome);

    expect(cpx_path('metadata.json'))->toBe("{$cpxHome}/metadata.json");
});

test('CPX_HOME takes precedence over the user home directory', function () {
    $cpxHome = $this->temporaryDirectory('cpx-home-override');
    $this->setEnvironmentVariable('HOME', $this->temporaryDirectory('cpx-home'));
    $this->setEnvironmentVariable('CPX_HOME', $cpxHome);

    expect(cpx_path('metadata.json'))->toBe("{$cpxHome}/metadata.json");
});

test('it trims a trailing slash from CPX_HOME', function () {
    $cpxHome = $this->temporaryDirectory('cpx-home-override');
    $this->setEnvironmentVariable('CPX_HOME', "{$cpxHome}/");

    expect(cpx_path('metadata.json'))->toBe("{$cpxHome}/metadata.json");
});

test('it falls back to the user home when CPX_HOME is empty', function () {
    $home = $this->temporaryDirectory('cpx-home');
    $this->setEnvironmentVariable('HOME', $home);
    $this->setEnvironmentVariable('CPX_HOME', '');

    expect(cpx_path('metadata.json'))->toBe("{$home}/.cpx/metadata.json");
});

test('cpx_path with no argument does not produce a trailing slash with CPX_HOME', function () {
    $cpxHome = $this->temporaryDirectory('cpx-home-override');
    $this->setEnvironmentVariable('CPX_HOME', $cpxHome);

    expect(cpx_path())->toBe($cpxHome);
});

test('cpx_path with no argument does not produce a trailing slash with HOME', function () {
    $home = $this->temporaryDirectory('cpx-home');
    $this->setEnvironmentVariable('HOME', $home);
    $this->setEnvironmentVariable('CPX_HOME', '');

    expect(cpx_path())->toBe("{$home}/.cpx");
});

test('it falls back to USERPROFILE when HOME is not set', function () {
    $home = $this->temporaryDirectory('cpx-home');
    $this->setEnvironmentVariable('CPX_HOME', '');
    $this->setEnvironmentVariable('HOME', '');
    $this->setEnvironmentVariable('USERPROFILE', $home);

    expect(cpx_path('metadata.json'))->toBe("{$home}/.cpx/metadata.json");
});

test('it falls back to HOMEDRIVE and HOMEPATH when HOME and USERPROFILE are not set', function () {
    $this->setEnvironmentVariable('CPX_HOME', '');
    $this->setEnvironmentVariable('HOME', '');
    $this->setEnvironmentVariable('USERPROFILE', '');
    $this->setEnvironmentVariable('HOMEDRIVE', 'C:');
    $this->setEnvironmentVariable('HOMEPATH', '\\Users\\cpx');

    expect(cpx_path('metadata.json'))->toBe('C:\\Users\\cpx/.cpx/metadata.json');
});

test('it trims a trailing backslash from CPX_HOME', function () {
    $cpxHome = $this->temporaryDirectory('cpx-home-override');
    $this->setEnvironmentVariable('CPX_HOME', "{$cpxHome}\\");

    expect(cpx_path('metadata.json'))->toBe("{$cpxHome}/metadata.json");
});

test('it trims a trailing backslash from the home directory', function () {
    $home = $this->temporaryDirectory('cpx-home');
    $this->setEnvironmentVariable('CPX_HOME', '');
    $this->setEnvironmentVariable('HOME', '');
    $this->setEnvironmentVariable('USERPROFILE', "{$home}\\");

    expect(cpx_path('metadata.json'))->toBe("{$home}/.cpx/metadata.json");
});

test('it fails loudly when neither CPX_HOME nor the home directory can be resolved', function () {
    foreach (['CPX_HOME', 'HOME', 'USERPROFILE', 'HOMEDRIVE', 'HOMEPATH'] as $variable) {
        $this->setEnvironmentVariable($variable, '');
    }

    expect(fn () => cpx_path('metadata.json'))
        ->toThrow(RuntimeException::class, 'Unable to determine the home directory; set the CPX_HOME, HOME, or USERPROFILE environment variable.');
});
