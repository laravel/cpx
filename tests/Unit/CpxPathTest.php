<?php

test('it stores cpx state under composer home when available', function () {
    $composerHome = $this->useIsolatedComposerHome();

    expect(cpx_path('metadata.json'))->toBe("{$composerHome}/.cpx/metadata.json");
});
