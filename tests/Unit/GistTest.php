<?php

declare(strict_types=1);

use Cpx\Exceptions\GistException;
use Cpx\Gists\Gist;
use Cpx\Gists\GistFile;

/**
 * @param  array<string, string|bool|null>  $overrides
 * @return array<string, mixed>
 */
function apiFile(string $filename, array $overrides = []): array
{
    return [
        'filename' => $filename,
        'language' => str_ends_with($filename, '.php') ? 'PHP' : null,
        'content' => str_ends_with($filename, '.php') ? "<?php echo '{$filename}';" : "content of {$filename}",
        'truncated' => false,
        'raw_url' => "https://gist.githubusercontent.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3/raw/{$filename}",
        ...$overrides,
    ];
}

test('builds gist files from an api payload', function () {
    $gist = Gist::fromApi([
        'files' => [
            'script.php' => apiFile('script.php', ['truncated' => true]),
        ],
    ]);

    expect($gist->files)->toHaveCount(1)
        ->and($gist->files[0]->filename)->toBe('script.php')
        ->and($gist->files[0]->language)->toBe('PHP')
        ->and($gist->files[0]->content)->toBe("<?php echo 'script.php';")
        ->and($gist->files[0]->truncated)->toBeTrue()
        ->and($gist->files[0]->rawUrl)->toContain('raw/script.php');
});

test('selects the single php file even alongside non-php files', function () {
    $gist = Gist::fromApi([
        'files' => [
            'README.md' => apiFile('README.md'),
            'script.php' => apiFile('script.php'),
            'LICENSE' => apiFile('LICENSE'),
        ],
    ]);

    expect($gist->select(null)->filename)->toBe('script.php');
});

test('detects php by language when the extension is missing', function () {
    $gist = Gist::fromApi([
        'files' => [
            'script' => apiFile('script', ['language' => 'PHP']),
        ],
    ]);

    expect($gist->select(null)->filename)->toBe('script');
});

test('detects php by extension when the language is null', function () {
    $file = new GistFile(filename: 'script.php', language: null, content: '<?php');

    expect($file->isPhp())->toBeTrue();
});

test('does not treat extensionless files as php from metadata alone', function () {
    $file = new GistFile(filename: 'script', language: null, content: "<?php echo 'hi';");

    expect($file->isPhp())->toBeFalse()
        ->and($file->hasPhpTag())->toBeTrue();
});

test('detects a php tag behind leading whitespace or a utf-8 bom', function (string $content) {
    $file = new GistFile(filename: 'script', language: null, content: $content);

    expect($file->hasPhpTag())->toBeTrue();
})->with([
    'leading newline' => "\n<?php echo 'hi';",
    'leading spaces' => "  <?php echo 'hi';",
    'utf-8 bom' => "\xEF\xBB\xBF<?php echo 'hi';",
]);

test('does not detect a php tag that only appears mid-content', function () {
    $file = new GistFile(filename: 'notes', language: null, content: 'Some notes about <?php scripts.');

    expect($file->hasPhpTag())->toBeFalse();
});

test('falls back to a leading php tag when metadata marks no file as php', function () {
    $gist = Gist::fromApi([
        'files' => [
            'script' => apiFile('script', ['content' => "<?php echo 'hi';"]),
            'README.md' => apiFile('README.md'),
        ],
    ]);

    expect($gist->select(null)->filename)->toBe('script');
});

test('metadata matches win over the php tag fallback', function () {
    $gist = Gist::fromApi([
        'files' => [
            'script.php' => apiFile('script.php'),
            'snippet' => apiFile('snippet', ['content' => "<?php echo 'hi';"]),
        ],
    ]);

    expect($gist->select(null)->filename)->toBe('script.php');
});

test('selects an extensionless file by fragment when it has a php tag', function () {
    $gist = Gist::fromApi([
        'files' => [
            'script' => apiFile('script', ['content' => "<?php echo 'hi';"]),
        ],
    ]);

    expect($gist->select('file-script')->filename)->toBe('script');
});

test('selects a php file by fragment from a multi-file gist', function () {
    $gist = Gist::fromApi([
        'files' => [
            'first.php' => apiFile('first.php'),
            'My Second Script.php' => apiFile('My Second Script.php'),
        ],
    ]);

    expect($gist->select('file-my-second-script-php')->filename)->toBe('My Second Script.php');
});

test('throws when the gist has no php files', function () {
    $gist = Gist::fromApi([
        'files' => [
            'README.md' => apiFile('README.md'),
        ],
    ]);

    $gist->select(null);
})->throws(GistException::class, 'The gist does not contain a PHP file.');

test('select asks the chooser when multiple php files qualify', function () {
    $gist = Gist::fromApi([
        'files' => [
            'first.php' => apiFile('first.php'),
            'second.php' => apiFile('second.php'),
            'README.md' => apiFile('README.md'),
        ],
    ]);

    $offered = null;

    $file = $gist->select(null, function (GistFile ...$files) use (&$offered): GistFile {
        $offered = array_map(fn (GistFile $file): string => $file->filename, $files);

        return $files[1];
    });

    expect($file->filename)->toBe('second.php')
        ->and($offered)->toBe(['first.php', 'second.php']);
});

test('select does not ask the chooser for a single php file', function () {
    $gist = Gist::fromApi([
        'files' => [
            'script.php' => apiFile('script.php'),
            'README.md' => apiFile('README.md'),
        ],
    ]);

    $file = $gist->select(null, fn (): GistFile => throw new RuntimeException('The chooser should not run.'));

    expect($file->filename)->toBe('script.php');
});

test('a fragment bypasses the chooser', function () {
    $gist = Gist::fromApi([
        'files' => [
            'first.php' => apiFile('first.php'),
            'second.php' => apiFile('second.php'),
        ],
    ]);

    $file = $gist->select('file-first-php', fn (): GistFile => throw new RuntimeException('The chooser should not run.'));

    expect($file->filename)->toBe('first.php');
});

test('throws when multiple php files exist without a fragment', function () {
    $gist = Gist::fromApi([
        'files' => [
            'first.php' => apiFile('first.php'),
            'second.php' => apiFile('second.php'),
        ],
    ]);

    $gist->select(null);
})->throws(GistException::class, 'The gist contains multiple PHP files (first.php, second.php).');

test('throws when the fragment matches no file', function () {
    $gist = Gist::fromApi([
        'files' => [
            'script.php' => apiFile('script.php'),
        ],
    ]);

    $gist->select('file-missing-php');
})->throws(GistException::class, "No gist file matches '#file-missing-php'.");

test('throws when the fragment matches a non-php file', function () {
    $gist = Gist::fromApi([
        'files' => [
            'README.md' => apiFile('README.md'),
            'script.php' => apiFile('script.php'),
        ],
    ]);

    $gist->select('file-readme-md');
})->throws(GistException::class, "The gist file 'README.md' is not a PHP script.");

test('throws on malformed payloads', function (array $payload) {
    Gist::fromApi($payload);
})->with([
    'missing files' => [[]],
    'empty files' => [['files' => []]],
    'non-array file entry' => [['files' => ['script.php' => 'oops']]],
    'file without filename' => [['files' => ['script.php' => ['content' => '<?php']]]],
    'file without content' => [['files' => ['script.php' => ['filename' => 'script.php', 'language' => 'PHP']]]],
    'truncated file without raw url' => [['files' => ['script.php' => ['filename' => 'script.php', 'language' => 'PHP', 'content' => '<?php', 'truncated' => true]]]],
])->throws(GistException::class, 'Unexpected response from the GitHub gists API.');
