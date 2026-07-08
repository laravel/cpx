<?php

use Cpx\Packages\LocalProject;
use Cpx\Support\Filesystem;

test('it discovers the nearest ancestor holding a composer.json', function () {
    $root = $this->temporaryDirectory('cpx-project');
    file_put_contents("{$root}/composer.json", json_encode([]));
    mkdir("{$root}/src/deep", 0755, true);

    $project = LocalProject::discover("{$root}/src/deep");

    expect($project)->not->toBeNull()
        ->and($project->root)->toBe($root);
});

test('it defaults the bin-dir to vendor/bin when config.bin-dir is absent', function () {
    $root = $this->temporaryDirectory('cpx-project');
    file_put_contents("{$root}/composer.json", json_encode([]));

    expect(LocalProject::discover($root)->binDir)->toBe('vendor/bin');
});

test('it reads a custom config.bin-dir', function () {
    $root = $this->temporaryDirectory('cpx-project');
    file_put_contents("{$root}/composer.json", json_encode(['config' => ['bin-dir' => 'tools']]));

    expect(LocalProject::discover($root)->binDir)->toBe('tools');
});

test('binaryPath returns the absolute path when the bin exists as a file', function () {
    $root = $this->temporaryDirectory('cpx-project');
    file_put_contents("{$root}/composer.json", json_encode([]));
    mkdir("{$root}/vendor/bin", 0755, true);
    writeExecutable("{$root}/vendor/bin/pint", noopBinary());

    $project = LocalProject::discover($root);

    expect($project->binaryPath('pint'))->toBe("{$project->root}/vendor/bin/pint");
});

test('binaryPath joins a custom bin-dir', function () {
    $root = $this->temporaryDirectory('cpx-project');
    file_put_contents("{$root}/composer.json", json_encode(['config' => ['bin-dir' => 'tools']]));
    mkdir("{$root}/tools", 0755, true);
    writeExecutable("{$root}/tools/pint", noopBinary());

    $project = LocalProject::discover($root);

    expect($project->binaryPath('pint'))->toBe("{$project->root}/tools/pint");
});

test('binaryPath honours an absolute bin-dir without joining the project root', function () {
    $root = $this->temporaryDirectory('cpx-project');
    $binDir = $this->temporaryDirectory('cpx-bin');
    file_put_contents("{$root}/composer.json", json_encode(['config' => ['bin-dir' => $binDir]]));
    writeExecutable("{$binDir}/pint", noopBinary());

    expect(LocalProject::discover($root)->binaryPath('pint'))->toBe("{$binDir}/pint");
});

test('binaryPath does not join Windows-style absolute bin-dirs onto the project root', function () {
    foreach (['C:\\tools', 'C:/tools', '\\\\server\\bins'] as $binDir) {
        $root = $this->temporaryDirectory('cpx-project');
        file_put_contents("{$root}/composer.json", json_encode(['config' => ['bin-dir' => $binDir]]));

        $wronglyJoined = "{$root}/{$binDir}";
        mkdir($wronglyJoined, 0755, true);
        writeExecutable("{$wronglyJoined}/pint", noopBinary());

        expect(LocalProject::discover($root)->binaryPath('pint'))->toBeNull();
    }
});

test('binaryPath returns null when the entry is missing', function () {
    $root = $this->temporaryDirectory('cpx-project');
    file_put_contents("{$root}/composer.json", json_encode([]));

    expect(LocalProject::discover($root)->binaryPath('pint'))->toBeNull();
});

test('binaryPath returns null when the entry is a directory', function () {
    $root = $this->temporaryDirectory('cpx-project');
    file_put_contents("{$root}/composer.json", json_encode([]));
    mkdir("{$root}/vendor/bin/pint", 0755, true);

    expect(LocalProject::discover($root)->binaryPath('pint'))->toBeNull();
});

test('discovery stops at the closest composer.json when projects are nested', function () {
    $outer = $this->temporaryDirectory('cpx-project');
    file_put_contents("{$outer}/composer.json", json_encode(['config' => ['bin-dir' => 'outer']]));
    mkdir("{$outer}/inner/src", 0755, true);
    file_put_contents("{$outer}/inner/composer.json", json_encode(['config' => ['bin-dir' => 'inner']]));

    $project = LocalProject::discover("{$outer}/inner/src");

    expect(Filesystem::normalizePath($project->root))->toBe(Filesystem::normalizePath("{$outer}/inner"))
        ->and($project->binDir)->toBe('inner');
});

test('discovery returns null when no composer.json exists up to the root', function () {
    $root = $this->temporaryDirectory('cpx-empty');

    expect(LocalProject::discover($root))->toBeNull();
});

test('discovery returns null when the composer.json is invalid JSON', function () {
    $root = $this->temporaryDirectory('cpx-project');
    file_put_contents("{$root}/composer.json", 'not-json{');

    expect(LocalProject::discover($root))->toBeNull();
});

test('installedPackageDir returns the path when the package is installed', function () {
    $root = $this->temporaryDirectory('cpx-project');
    file_put_contents("{$root}/composer.json", json_encode([]));
    mkdir("{$root}/vendor/laravel/pint", 0755, true);

    $project = LocalProject::discover($root);

    expect($project->installedPackageDir('laravel', 'pint'))->toBe("{$project->root}/vendor/laravel/pint")
        ->and($project->installedPackageDir('laravel', 'missing'))->toBeNull();
});

test('installedVersion reads the version from installed.json', function () {
    $root = $this->temporaryDirectory('cpx-project');
    file_put_contents("{$root}/composer.json", json_encode([]));
    mkdir("{$root}/vendor/composer", 0755, true);
    file_put_contents("{$root}/vendor/composer/installed.json", json_encode([
        'packages' => [
            ['name' => 'laravel/pint', 'version' => 'v2.1.0'],
        ],
    ]));

    expect(LocalProject::discover($root)->installedVersion('laravel', 'pint'))->toBe('v2.1.0');
});

test('installedVersion returns null when the package or installed.json is missing or invalid', function () {
    $root = $this->temporaryDirectory('cpx-project');
    file_put_contents("{$root}/composer.json", json_encode([]));

    $project = LocalProject::discover($root);

    expect($project->installedVersion('laravel', 'pint'))->toBeNull();

    mkdir("{$root}/vendor/composer", 0755, true);
    file_put_contents("{$root}/vendor/composer/installed.json", 'not-json{');

    expect($project->installedVersion('laravel', 'pint'))->toBeNull();
});
