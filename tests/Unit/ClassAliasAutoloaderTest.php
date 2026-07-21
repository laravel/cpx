<?php

use Cpx\Runtime\ClassAliasAutoloader;

/**
 * @param  array<string, string>  $classmap
 * @param  array<string, list<string>>  $psr4
 */
function aliasAutoloadRoot(string $root, array $classmap = [], array $psr4 = []): void
{
    mkdir("{$root}/vendor/composer", 0755, true);
    file_put_contents("{$root}/vendor/composer/autoload_classmap.php", '<?php return '.var_export($classmap, true).';');
    file_put_contents("{$root}/vendor/composer/autoload_psr4.php", '<?php return '.var_export($psr4, true).';');
}

test('loadable classmap entries register short-name aliases', function () {
    $root = $this->temporaryDirectory('cpx-alias');
    aliasAutoloadRoot($root, classmap: ['Cpx\Runtime\CpxRequire' => 'src/Runtime/CpxRequire.php']);

    $autoloader = new ClassAliasAutoloader;
    $autoloader->addAliases($root);
    $autoloader->aliasClass('CpxRequire');

    expect(class_exists('CpxRequire', false))->toBeTrue()
        ->and((new ReflectionClass('CpxRequire'))->getName())->toBe('Cpx\Runtime\CpxRequire');
});

test('classmap entries that cannot be loaded are never aliased', function () {
    $root = $this->temporaryDirectory('cpx-alias');
    aliasAutoloadRoot($root, classmap: ['Cpx\Missing\GhostClass' => 'src/Missing/GhostClass.php']);

    $autoloader = new ClassAliasAutoloader;
    $autoloader->addAliases($root);
    $autoloader->aliasClass('GhostClass');

    expect(class_exists('GhostClass', false))->toBeFalse();
});

test('already-taken short names keep their first classmap registration', function () {
    $root = $this->temporaryDirectory('cpx-alias');
    aliasAutoloadRoot($root, classmap: [
        'Cpx\Support\Filesystem' => 'src/Support/Filesystem.php',
        'Composer\Util\Filesystem' => 'src/Util/Filesystem.php',
    ]);

    $autoloader = new ClassAliasAutoloader;
    $autoloader->addAliases($root);
    $autoloader->aliasClass('Filesystem');

    expect((new ReflectionClass('Filesystem'))->getName())->toBe('Cpx\Support\Filesystem');
});

test('psr4 roots register aliases for their php files', function () {
    $root = $this->temporaryDirectory('cpx-alias');
    $sources = $this->temporaryDirectory('cpx-alias-src');
    file_put_contents("{$sources}/ExecVariable.php", '<?php');
    aliasAutoloadRoot($root, psr4: ['Cpx\Runtime\\' => [$sources]]);

    $autoloader = new ClassAliasAutoloader;
    $autoloader->addAliases($root);
    $autoloader->aliasClass('ExecVariable');

    expect(class_exists('ExecVariable', false))->toBeTrue()
        ->and((new ReflectionClass('ExecVariable'))->getName())->toBe('Cpx\Runtime\ExecVariable');
});

test('aliasClass ignores unknown and namespace-qualified names', function () {
    $autoloader = new ClassAliasAutoloader;

    $autoloader->aliasClass('TotallyUnknownClass');
    $autoloader->aliasClass('Cpx\Runtime\Context');

    expect(class_exists('TotallyUnknownClass', false))->toBeFalse();
});
