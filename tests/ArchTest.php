<?php

declare(strict_types=1);

arch()->preset()->php();

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('it will not use risky security functions')
    ->expect([
        'md5',
        'sha1',
        'uniqid',
        'rand',
        'mt_rand',
        'tempnam',
        'str_shuffle',
        'shuffle',
        'array_rand',
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'create_function',
        'unserialize',
        'extract',
        'mb_parse_str',
        'dl',
        'assert',
    ])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('Cpx')
    ->toUseStrictTypes();

test('only the process runner calls proc_open', function () {
    $offenders = sourceFilesContaining('proc_open', except: ['src/Process/ProcessRunner.php']);

    expect($offenders)->toBe([]);
});

/**
 * @return list<string>
 */
function sourceFilesContaining(string $needle, array $except = []): array
{
    $files = [];
    $root = realpath(__DIR__.'/..');

    if ($root === false) {
        return [];
    }

    $except[] = 'tests/ArchTest.php';

    foreach (['src', 'tests'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$directory}"));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());

            if (in_array($path, $except, true)) {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), $needle)) {
                $files[] = $path;
            }
        }
    }

    sort($files);

    return $files;
}
