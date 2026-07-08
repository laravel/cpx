<?php

use Cpx\Packages\BinExecutable;

test('it prefers a .bat sibling on windows', function () {
    BinExecutable::fakeWindows();

    $directory = $this->temporaryDirectory('cpx-bin');
    $path = "{$directory}/pint";

    writeExecutable($path, noopBinary());
    file_put_contents("{$path}.bat", "@ECHO OFF\r\n");

    expect(BinExecutable::commandFor($path, ['--test']))->toBe(["{$path}.bat", '--test']);
});

test('it uses a .cmd sibling on windows when no .bat exists', function () {
    BinExecutable::fakeWindows();

    $directory = $this->temporaryDirectory('cpx-bin');
    $path = "{$directory}/pint";

    writeExecutable($path, noopBinary());
    file_put_contents("{$path}.cmd", "@ECHO OFF\r\n");

    expect(BinExecutable::commandFor($path))->toBe(["{$path}.cmd"]);
});

test('it ignores proxy siblings on posix', function () {
    BinExecutable::fakeWindows(false);

    $directory = $this->temporaryDirectory('cpx-bin');
    $path = "{$directory}/pint";

    writeExecutable($path, noopBinary());
    file_put_contents("{$path}.bat", "@ECHO OFF\r\n");

    expect(BinExecutable::commandFor($path, ['--test']))->toBe([PHP_BINARY, $path, '--test']);
});

test('it runs shebang php scripts through the interpreter', function () {
    BinExecutable::fakeWindows(false);

    $directory = $this->temporaryDirectory('cpx-bin');
    $path = "{$directory}/psysh";

    writeExecutable($path, "#!/usr/bin/env php\n<?php exit(0);\n");

    expect(BinExecutable::commandFor($path, ['--version']))->toBe([PHP_BINARY, $path, '--version']);
});

test('it runs plain php files through the interpreter', function () {
    BinExecutable::fakeWindows(false);

    $directory = $this->temporaryDirectory('cpx-bin');
    $path = "{$directory}/tool";

    writeExecutable($path, "<?php exit(0);\n");

    expect(BinExecutable::commandFor($path))->toBe([PHP_BINARY, $path]);
});

test('it runs php scripts through the interpreter on windows when no sibling exists', function () {
    BinExecutable::fakeWindows();

    $directory = $this->temporaryDirectory('cpx-bin');
    $path = "{$directory}/pint";

    writeExecutable($path, noopBinary());

    expect(BinExecutable::commandFor($path))->toBe([PHP_BINARY, $path]);
});

test('it runs non-php files as-is', function () {
    BinExecutable::fakeWindows(false);

    $directory = $this->temporaryDirectory('cpx-bin');
    $path = "{$directory}/tool";

    writeExecutable($path, "#!/bin/sh\nexit 0\n");

    expect(BinExecutable::commandFor($path, ['--flag']))->toBe([$path, '--flag']);
});

test('it runs unreadable paths as-is', function () {
    BinExecutable::fakeWindows(false);

    $directory = $this->temporaryDirectory('cpx-bin');
    $path = "{$directory}/missing";

    expect(BinExecutable::commandFor($path))->toBe([$path]);
});
