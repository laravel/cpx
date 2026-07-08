<?php

use Cpx\Runtime\PhpExecutionHelper;
use Cpx\Support\Filesystem;

test('it finds the autoloader in an ancestor directory', function () {
    unset($GLOBALS['cpxAutoloadHits']);

    $root = $this->temporaryDirectory('cpx-exec');
    mkdir("{$root}/vendor", 0755, true);
    mkdir("{$root}/nested/deep", 0755, true);
    file_put_contents("{$root}/vendor/autoload.php", '<?php $GLOBALS["cpxAutoloadHits"][] = __FILE__;');

    PhpExecutionHelper::init("{$root}/nested/deep", shouldAliasClasses: false);

    $hits = array_map(Filesystem::normalizePath(...), $GLOBALS['cpxAutoloadHits'] ?? []);

    expect($hits)->toBe([Filesystem::normalizePath("{$root}/vendor/autoload.php")]);

    unset($GLOBALS['cpxAutoloadHits']);
});

test('it returns when no autoloader exists in any ancestor directory', function () {
    unset($GLOBALS['cpxAutoloadHits']);

    $root = $this->temporaryDirectory('cpx-exec');
    mkdir("{$root}/nested", 0755, true);

    PhpExecutionHelper::init("{$root}/nested", shouldAliasClasses: false);

    expect($GLOBALS['cpxAutoloadHits'] ?? [])->toBe([]);
});
