<?php

use Cpx\Runtime\Context;
use Cpx\Runtime\PhpExecutionHelper;
use Cpx\Support\Filesystem;

function laravelSpyFixture(string $root): void
{
    mkdir("{$root}/vendor", 0755, true);
    mkdir("{$root}/bootstrap", 0755, true);

    file_put_contents("{$root}/vendor/autoload.php", '<?php $GLOBALS["cpxPrepareAutoloaded"] = true;');
    file_put_contents("{$root}/artisan", '<?php');
    file_put_contents("{$root}/bootstrap/app.php", <<<'PHP'
    <?php

    return new class
    {
        public function make(string $abstract): object
        {
            return new class
            {
                public function bootstrap(): void
                {
                    $GLOBALS['cpxKernelBootstrapped'] = true;
                }
            };
        }
    };
    PHP);
}

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

test('prepare loads the autoloader and boots the resolved loader', function () {
    unset($GLOBALS['cpxPrepareAutoloaded'], $GLOBALS['cpxKernelBootstrapped']);

    $root = $this->temporaryDirectory('cpx-prepare');
    laravelSpyFixture($root);

    $variables = PhpExecutionHelper::prepare(new Context($root, $root, shouldAliasClasses: false));

    expect($variables)->toHaveKey('app')
        ->and($variables['app'])->toBeObject()
        ->and($GLOBALS['cpxPrepareAutoloaded'] ?? false)->toBeTrue()
        ->and($GLOBALS['cpxKernelBootstrapped'] ?? false)->toBeTrue();

    unset($GLOBALS['cpxPrepareAutoloaded'], $GLOBALS['cpxKernelBootstrapped']);
});

test('prepare skips the framework boot when disabled', function () {
    unset($GLOBALS['cpxKernelBootstrapped']);

    $root = $this->temporaryDirectory('cpx-prepare');
    laravelSpyFixture($root);

    $variables = PhpExecutionHelper::prepare(new Context($root, $root, shouldBoot: false, shouldAliasClasses: false));

    expect($variables)->toBe([])
        ->and($GLOBALS['cpxKernelBootstrapped'] ?? false)->toBeFalse();
});

test('prepare returns nothing without an autoload root', function () {
    $root = $this->temporaryDirectory('cpx-prepare');

    expect(PhpExecutionHelper::prepare(new Context($root, null)))->toBe([]);
});

test('it finds the autoload root from a nested directory', function () {
    $root = $this->temporaryDirectory('cpx-exec');
    mkdir("{$root}/vendor", 0755, true);
    mkdir("{$root}/nested/deep", 0755, true);
    file_put_contents("{$root}/vendor/autoload.php", '<?php');

    expect(PhpExecutionHelper::findAutoloadRoot("{$root}/nested/deep"))->toBe($root);
});

test('it returns null when no autoload root exists', function () {
    $root = $this->temporaryDirectory('cpx-exec');
    mkdir("{$root}/nested", 0755, true);

    expect(PhpExecutionHelper::findAutoloadRoot("{$root}/nested"))->toBeNull();
});

test('it returns when no autoloader exists in any ancestor directory', function () {
    unset($GLOBALS['cpxAutoloadHits']);

    $root = $this->temporaryDirectory('cpx-exec');
    mkdir("{$root}/nested", 0755, true);

    PhpExecutionHelper::init("{$root}/nested", shouldAliasClasses: false);

    expect($GLOBALS['cpxAutoloadHits'] ?? [])->toBe([]);

    unset($GLOBALS['cpxAutoloadHits']);
});
