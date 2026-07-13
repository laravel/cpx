<?php

declare(strict_types=1);

use Cpx\Exceptions\GistException;
use Cpx\Gists\GistClient;
use Cpx\Gists\GistFile;
use Cpx\Gists\GistUrl;

const GIST_URL = 'https://gist.github.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3';

function fakeGist(string $content, string $filename = 'script.php'): void
{
    GistClient::fake(fn (GistUrl $url): GistFile => new GistFile(
        filename: $filename,
        language: 'PHP',
        content: $content,
    ));
}

/** @return list<string> */
function gistTempFiles(): array
{
    return glob(sys_get_temp_dir().'/cpx-gist-*.php') ?: [];
}

test('exec runs a gist url in a child process', function () {
    $directory = $this->temporaryDirectory('cpx-exec-gist');
    $this->useWorkingDirectory($directory);
    $marker = "{$directory}/marker.txt";

    fakeGist('<?php file_put_contents('.var_export($marker, true).', "ran-gist");');

    [$status] = runCpxCommand(['exec', GIST_URL]);

    expect($status)->toBe(0)
        ->and(file_get_contents($marker))->toBe('ran-gist');
});

test('exec passes the gist id and fragment to the client', function () {
    $directory = $this->temporaryDirectory('cpx-exec-gist');
    $this->useWorkingDirectory($directory);

    $received = null;

    GistClient::fake(function (GistUrl $url) use (&$received): GistFile {
        $received = $url;

        return new GistFile(filename: 'second.php', language: 'PHP', content: '<?php');
    });

    [$status] = runCpxCommand(['exec', GIST_URL.'#file-second-php']);

    expect($status)->toBe(0)
        ->and($received?->id)->toBe('aa5a8f8cbc4f1e502dbb3ca546a4cbf3')
        ->and($received?->fragment)->toBe('file-second-php');
});

test('exec gist loads the nearest autoloader', function () {
    $directory = $this->temporaryDirectory('cpx-exec-gist');
    $this->useWorkingDirectory($directory);
    $marker = "{$directory}/marker.txt";

    mkdir("{$directory}/vendor", 0755, true);
    file_put_contents("{$directory}/vendor/autoload.php", '<?php file_put_contents('.var_export($marker, true).', "autoloaded");');

    fakeGist('<?php clearstatcache();');

    [$status] = runCpxCommand(['exec', GIST_URL]);

    expect($status)->toBe(0)
        ->and(file_get_contents($marker))->toBe('autoloaded');
});

test('exec forwards the gist exit code', function () {
    $directory = $this->temporaryDirectory('cpx-exec-gist');
    $this->useWorkingDirectory($directory);

    fakeGist('<?php exit(3);');

    [$status] = runCpxCommand(['exec', GIST_URL]);

    expect($status)->toBe(3);
});

test('exec reports gist failures and exits with an error', function () {
    $directory = $this->temporaryDirectory('cpx-exec-gist');
    $this->useWorkingDirectory($directory);

    GistClient::fake(function (GistUrl $url): GistFile {
        throw GistException::notPhpGist();
    });

    [$status, $output] = runCpxCommand(['exec', GIST_URL]);

    expect($status)->toBe(1)
        ->and($output)->toContain('The gist does not contain a PHP file.');
});

test('exec deletes the gist temp file after a successful run', function () {
    $directory = $this->temporaryDirectory('cpx-exec-gist');
    $this->useWorkingDirectory($directory);

    $before = gistTempFiles();

    fakeGist('<?php clearstatcache();');

    [$status] = runCpxCommand(['exec', GIST_URL]);

    expect($status)->toBe(0)
        ->and(gistTempFiles())->toBe($before);
});

test('exec deletes the gist temp file after a failing run', function () {
    $directory = $this->temporaryDirectory('cpx-exec-gist');
    $this->useWorkingDirectory($directory);

    $before = gistTempFiles();

    fakeGist('<?php exit(5);');

    [$status] = runCpxCommand(['exec', GIST_URL]);

    expect($status)->toBe(5)
        ->and(gistTempFiles())->toBe($before);
});

test('exec passes a pinned revision to the client', function () {
    $directory = $this->temporaryDirectory('cpx-exec-gist');
    $this->useWorkingDirectory($directory);

    $received = null;

    GistClient::fake(function (GistUrl $url) use (&$received): GistFile {
        $received = $url;

        return new GistFile(filename: 'script.php', language: 'PHP', content: '<?php');
    });

    $revision = str_repeat('5c30e34c', 5);

    [$status] = runCpxCommand(['exec', GIST_URL."/{$revision}"]);

    expect($status)->toBe(0)
        ->and($received?->revision)->toBe($revision);
});

test('exec runs a raw gist url in a child process', function () {
    $directory = $this->temporaryDirectory('cpx-exec-gist');
    $this->useWorkingDirectory($directory);
    $marker = "{$directory}/marker.txt";

    $received = null;

    GistClient::fake(function (GistUrl $url) use (&$received, $marker): GistFile {
        $received = $url;

        return new GistFile(
            filename: 'script.php',
            language: null,
            content: '<?php file_put_contents('.var_export($marker, true).', "ran-raw-gist");',
        );
    });

    [$status] = runCpxCommand(['exec', 'https://gist.githubusercontent.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3/raw/55cc3f108fcf0cea924596fc1f00c9285e93e14d/script.php']);

    expect($status)->toBe(0)
        ->and(file_get_contents($marker))->toBe('ran-raw-gist')
        ->and($received?->rawUrl)->toContain('/raw/55cc3f108fcf0cea924596fc1f00c9285e93e14d/script.php');
});

test('exec reports unsupported gist urls', function (string $target) {
    $directory = $this->temporaryDirectory('cpx-exec-gist');
    $this->useWorkingDirectory($directory);

    [$status, $output] = runCpxCommand(['exec', $target]);

    expect($status)->toBe(1)
        ->and($output)->toContain('Unable to parse the gist URL');
})->with([
    'raw gist url without a file name' => 'https://gist.githubusercontent.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3/raw/',
    'non-hex id' => 'https://gist.github.com/WendellAdriel/not-a-gist-id',
]);

test('exec still reports missing local files as missing', function () {
    $directory = $this->temporaryDirectory('cpx-exec-gist');
    $this->useWorkingDirectory($directory);

    [$status, $output] = runCpxCommand(['exec', 'missing.php']);

    expect($status)->toBe(1)
        ->and($output)->toContain("File does not exist at 'missing.php'");
});
