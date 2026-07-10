<?php

use Cpx\Runtime\ComposerRequire;

function fakeSandboxBin(string $directory, string $script): string
{
    $bin = "{$directory}/cpx-stub.php";
    file_put_contents($bin, $script);

    return $bin;
}

test('it loads the sandbox autoloader reported by the cpx sandbox process', function () {
    $directory = $this->temporaryDirectory('cpx-composer-require');
    $marker = uniqid('cpxSandboxLoaded');

    mkdir("{$directory}/sandbox/vendor", 0755, true);
    file_put_contents(
        "{$directory}/sandbox/vendor/autoload.php",
        '<?php $GLOBALS['.var_export($marker, true).'] = true;',
    );

    $bin = fakeSandboxBin($directory, sprintf(
        '<?php file_put_contents(%s, json_encode(array_slice($argv, 1))); echo "install noise\n%s\n";',
        var_export("{$directory}/argv.json", true),
        "{$directory}/sandbox",
    ));
    $this->setEnvironmentVariable('CPX_EXEC_BIN', $bin);

    ob_start();
    ComposerRequire::load('vendor/package');
    $echoed = (string) ob_get_clean();

    expect($GLOBALS[$marker] ?? false)->toBeTrue()
        ->and(json_decode((string) file_get_contents("{$directory}/argv.json"), true))->toBe([ComposerRequire::COMMAND, 'vendor/package'])
        ->and($echoed)->toContain('install noise');

    unset($GLOBALS[$marker]);
});

test('it fails clearly when the cpx binary location is missing', function () {
    $this->setEnvironmentVariable('CPX_EXEC_BIN', '');

    ComposerRequire::load('vendor/package');
})->throws(RuntimeException::class, 'CPX_EXEC_BIN');

test('it fails when the sandbox process exits with an error', function () {
    $directory = $this->temporaryDirectory('cpx-composer-require');

    $bin = fakeSandboxBin($directory, '<?php exit(1);');
    $this->setEnvironmentVariable('CPX_EXEC_BIN', $bin);

    ComposerRequire::load('vendor/package');
})->throws(RuntimeException::class, 'Failed to install: vendor/package');

test('it fails when the reported sandbox has no autoloader', function () {
    $directory = $this->temporaryDirectory('cpx-composer-require');
    mkdir("{$directory}/sandbox", 0755, true);

    $bin = fakeSandboxBin($directory, sprintf('<?php echo "%s\n";', "{$directory}/sandbox"));
    $this->setEnvironmentVariable('CPX_EXEC_BIN', $bin);

    ComposerRequire::load('vendor/package');
})->throws(RuntimeException::class, 'Autoload file not found');
