<?php

declare(strict_types=1);

use Cpx\Gists\GistUrl;

test('parses a gist url with a username', function () {
    $url = GistUrl::tryFrom('https://gist.github.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3');

    expect($url)->not->toBeNull()
        ->and($url->id)->toBe('aa5a8f8cbc4f1e502dbb3ca546a4cbf3')
        ->and($url->revision)->toBeNull()
        ->and($url->fragment)->toBeNull();
});

test('parses a gist url without a username', function () {
    $url = GistUrl::tryFrom('https://gist.github.com/aa5a8f8cbc4f1e502dbb3ca546a4cbf3');

    expect($url)->not->toBeNull()
        ->and($url->id)->toBe('aa5a8f8cbc4f1e502dbb3ca546a4cbf3');
});

test('parses a legacy 20 character gist id', function () {
    $url = GistUrl::tryFrom('https://gist.github.com/WendellAdriel/0123456789abcdef0123');

    expect($url)->not->toBeNull()
        ->and($url->id)->toBe('0123456789abcdef0123');
});

test('accepts a trailing slash', function () {
    $url = GistUrl::tryFrom('https://gist.github.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3/');

    expect($url)->not->toBeNull()
        ->and($url->id)->toBe('aa5a8f8cbc4f1e502dbb3ca546a4cbf3');
});

test('accepts a scheme-less gist url', function () {
    $url = GistUrl::tryFrom('gist.github.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3');

    expect($url)->not->toBeNull()
        ->and($url->id)->toBe('aa5a8f8cbc4f1e502dbb3ca546a4cbf3');
});

test('captures the file fragment', function () {
    $url = GistUrl::tryFrom('https://gist.github.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3#file-my-script-php');

    expect($url)->not->toBeNull()
        ->and($url->fragment)->toBe('file-my-script-php');
});

test('returns null for non-gist targets', function (string $target) {
    expect(GistUrl::tryFrom($target))->toBeNull();
})->with([
    'relative path' => 'script.php',
    'absolute path' => '/home/user/script.php',
    'repository url' => 'https://github.com/WendellAdriel/cpx',
    'raw gist url' => 'https://gist.githubusercontent.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3/raw/script.php',
    'non-hex id' => 'https://gist.github.com/WendellAdriel/not-a-gist-id',
    'non-hex revision segment' => 'https://gist.github.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3/latest',
    'empty string' => '',
]);

test('parses a revision-pinned gist url', function () {
    $url = GistUrl::tryFrom('https://gist.github.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3/5c30e34cbe89298a1e12b6ba816eef25d15fdbcc');

    expect($url)->not->toBeNull()
        ->and($url->id)->toBe('aa5a8f8cbc4f1e502dbb3ca546a4cbf3')
        ->and($url->revision)->toBe('5c30e34cbe89298a1e12b6ba816eef25d15fdbcc');
});

test('parses a revision-pinned gist url with a fragment', function () {
    $url = GistUrl::tryFrom('https://gist.github.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3/5c30e34cbe89298a1e12b6ba816eef25d15fdbcc#file-my-script-php');

    expect($url?->revision)->toBe('5c30e34cbe89298a1e12b6ba816eef25d15fdbcc')
        ->and($url?->fragment)->toBe('file-my-script-php');
});

test('recognizes gist hosts', function (string $target) {
    expect(GistUrl::isGistHost($target))->toBeTrue();
})->with([
    'gist page' => 'https://gist.github.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3',
    'scheme-less url' => 'gist.github.com/WendellAdriel/not-a-gist-id',
    'raw gist url' => 'https://gist.githubusercontent.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3/raw/script.php',
]);

test('does not treat other targets as gist hosts', function (string $target) {
    expect(GistUrl::isGistHost($target))->toBeFalse();
})->with([
    'local path' => 'script.php',
    'repository url' => 'https://github.com/WendellAdriel/cpx',
]);
