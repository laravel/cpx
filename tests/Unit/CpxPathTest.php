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

    expect(cpx_path('metadata.json'))->toBe("{$home}/.cpx/metadata.json");
});
