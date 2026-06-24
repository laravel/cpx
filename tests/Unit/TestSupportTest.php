<?php

test('it isolates home and composer home during tests', function () {
    $composerHome = $this->useIsolatedComposerHome();

    expect(getenv('COMPOSER_HOME'))->toBe($composerHome)
        ->and($_SERVER['COMPOSER_HOME'])->toBe($composerHome)
        ->and(is_dir($composerHome))->toBeTrue();
});
