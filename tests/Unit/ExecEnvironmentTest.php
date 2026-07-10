<?php

use Cpx\Runtime\Environment;
use Cpx\Runtime\ExecEnvironment;

test('it serializes defaults with unset file and code', function () {
    expect((new ExecEnvironment)->toEnvironment())->toBe([
        'CPX_EXEC_FILE' => false,
        'CPX_EXEC_CODE' => false,
        'CPX_EXEC_FIND_AUTOLOADER' => '1',
        'CPX_EXEC_BOOT' => '1',
        'CPX_EXEC_ALIAS' => '1',
        'CPX_EXEC_VERBOSE' => '0',
        'CPX_EXEC_BIN' => dirname(__DIR__, 2).'/cpx',
    ]);
});

test('it serializes explicit values', function () {
    $environment = new ExecEnvironment(
        file: '/project/script.php',
        code: 'exit(0);',
        findAutoloader: false,
        boot: false,
        aliasClasses: false,
        verbose: true,
    );

    expect($environment->toEnvironment())->toBe([
        'CPX_EXEC_FILE' => '/project/script.php',
        'CPX_EXEC_CODE' => 'exit(0);',
        'CPX_EXEC_FIND_AUTOLOADER' => '0',
        'CPX_EXEC_BOOT' => '0',
        'CPX_EXEC_ALIAS' => '0',
        'CPX_EXEC_VERBOSE' => '1',
        'CPX_EXEC_BIN' => dirname(__DIR__, 2).'/cpx',
    ]);
});

test('it points the child at the phar when running from one', function () {
    Environment::fakePharPath('/opt/cpx.phar');

    expect((new ExecEnvironment)->toEnvironment()['CPX_EXEC_BIN'])->toBe('/opt/cpx.phar');
});
