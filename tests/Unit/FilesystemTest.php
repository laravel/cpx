<?php

use Cpx\Support\Filesystem;

test('writeAtomic writes the full contents and leaves no temp residue', function () {
    $directory = $this->temporaryDirectory('cpx-fs');
    $path = "{$directory}/data.json";

    Filesystem::writeAtomic($path, '{"hello":"world"}');

    expect(file_get_contents($path))->toBe('{"hello":"world"}')
        ->and(glob("{$directory}/*.tmp"))->toBe([]);
});

test('writeAtomic overwrites an existing file without truncation residue', function () {
    $directory = $this->temporaryDirectory('cpx-fs');
    $path = "{$directory}/data.json";

    file_put_contents($path, 'previous contents that are noticeably longer');
    Filesystem::writeAtomic($path, 'new');

    expect(file_get_contents($path))->toBe('new')
        ->and(glob("{$directory}/*.tmp"))->toBe([]);
});

test('deleteDirectoryWithin removes a directory genuinely inside the root', function () {
    $root = $this->temporaryDirectory('cpx-root');
    $target = "{$root}/laravel/pint/latest";

    mkdir($target, 0755, true);
    file_put_contents("{$target}/composer.json", '{}');

    Filesystem::deleteDirectoryWithin($target, $root);

    expect(is_dir($target))->toBeFalse();
});

test('deleteDirectoryWithin refuses to delete a path resolving outside the root', function () {
    $base = $this->temporaryDirectory('cpx-base');
    $root = "{$base}/root";
    $escape = "{$base}/escape";

    mkdir($root, 0755, true);
    mkdir($escape, 0755, true);
    file_put_contents("{$escape}/keep.txt", 'x');

    expect(fn () => Filesystem::deleteDirectoryWithin("{$root}/../escape", $root))
        ->toThrow(RuntimeException::class);

    expect(is_dir($escape))->toBeTrue()
        ->and(file_exists("{$escape}/keep.txt"))->toBeTrue();
});

test('deleteDirectoryWithin refuses to follow a symlink escaping the root', function () {
    $base = $this->temporaryDirectory('cpx-symlink');
    $root = "{$base}/root";
    $outside = "{$base}/outside";

    mkdir($root, 0755, true);
    mkdir($outside, 0755, true);
    file_put_contents("{$outside}/keep.txt", 'x');
    symlink($outside, "{$root}/link");

    expect(fn () => Filesystem::deleteDirectoryWithin("{$root}/link", $root))
        ->toThrow(RuntimeException::class);

    expect(is_dir($outside))->toBeTrue()
        ->and(file_exists("{$outside}/keep.txt"))->toBeTrue();
});

test('pruneEmptyParents removes empty parent directories up to the root', function () {
    $root = $this->temporaryDirectory('cpx-prune');
    $leaf = "{$root}/laravel/pint/latest";

    mkdir($leaf, 0755, true);
    Filesystem::deleteDirectory($leaf);

    Filesystem::pruneEmptyParents($leaf, $root);

    expect(is_dir("{$root}/laravel/pint"))->toBeFalse()
        ->and(is_dir("{$root}/laravel"))->toBeFalse()
        ->and(is_dir($root))->toBeTrue();
});

test('pruneEmptyParents keeps a parent that still holds another version', function () {
    $root = $this->temporaryDirectory('cpx-prune');
    $removed = "{$root}/laravel/pint/latest";
    $sibling = "{$root}/laravel/pint/3.0";

    mkdir($removed, 0755, true);
    mkdir($sibling, 0755, true);
    Filesystem::deleteDirectory($removed);

    Filesystem::pruneEmptyParents($removed, $root);

    expect(is_dir($sibling))->toBeTrue()
        ->and(is_dir("{$root}/laravel/pint"))->toBeTrue()
        ->and(is_dir("{$root}/laravel"))->toBeTrue();
});

test('pruneEmptyParents never removes the cache root', function () {
    $root = $this->temporaryDirectory('cpx-prune');
    $leaf = "{$root}/vendor/name";

    mkdir($leaf, 0755, true);
    Filesystem::deleteDirectory($leaf);

    Filesystem::pruneEmptyParents($leaf, $root);

    expect(is_dir("{$root}/vendor"))->toBeFalse()
        ->and(is_dir($root))->toBeTrue();
});

test('pruneEmptyParents leaves directories outside the root untouched', function () {
    $base = $this->temporaryDirectory('cpx-prune');
    $root = "{$base}/root";
    $outside = "{$base}/outside/child";

    mkdir($root, 0755, true);
    mkdir($outside, 0755, true);
    Filesystem::deleteDirectory($outside);

    Filesystem::pruneEmptyParents($outside, $root);

    expect(is_dir("{$base}/outside"))->toBeTrue();
});
