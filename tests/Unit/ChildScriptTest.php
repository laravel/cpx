<?php

use Cpx\Runtime\Environment;
use Cpx\Support\ChildScript;

test('it returns the bundled script path when not running from a phar', function () {
    $expected = dirname(__DIR__, 2).'/files/exec-bootstrap.php';

    expect(ChildScript::path('exec-bootstrap.php'))->toBe($expected);
});

test('it materializes a phar stub outside the phar', function () {
    $this->useIsolatedComposerHome();
    Environment::fakePharPath('/opt/cpx.phar');

    $path = ChildScript::path('exec-bootstrap.php');

    expect($path)->toBe(cpx_path('exec-bootstrap.php'))
        ->and((string) file_get_contents((string) $path))
        ->toContain("Phar::loadPhar('/opt/cpx.phar', 'cpx.phar')")
        ->toContain("return require 'phar://cpx.phar/files/exec-bootstrap.php';");
});
