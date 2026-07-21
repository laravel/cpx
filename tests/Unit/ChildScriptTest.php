<?php

use Cpx\Runtime\Environment;
use Cpx\Support\ChildScript;

test('the child runtime autoloader declines unknown classes', function () {
    $process = proc_open(
        [
            PHP_BINARY,
            '-r',
            'require $argv[1]; var_dump(class_exists("Cpx\\\\Runtime\\\\Missing"));',
            '--',
            dirname(__DIR__, 2).'/files/child-runtime.php',
        ],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    assert(is_resource($process));

    $output = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);

    expect($status)->toBe(0)
        ->and($output)->toContain('bool(false)');
});

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
