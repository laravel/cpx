<?php

declare(strict_types=1);

use Cpx\Exceptions\GistException;
use Cpx\Gists\GistClient;
use Cpx\Gists\GistFile;
use Cpx\Gists\GistUrl;

function gistUrl(?string $fragment = null): GistUrl
{
    $anchor = $fragment === null ? '' : "#{$fragment}";
    $url = GistUrl::tryFrom("https://gist.github.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3{$anchor}");

    assert($url instanceof GistUrl);

    return $url;
}

/** @param array<string, string> $responses */
function stubbedGistClient(array $responses): GistClient
{
    return new class($responses) extends GistClient
    {
        /** @param array<string, string> $responses */
        public function __construct(private readonly array $responses) {}

        protected function httpGet(string $url): string
        {
            if (! array_key_exists($url, $this->responses)) {
                throw GistException::downloadFailed($url);
            }

            return $this->responses[$url];
        }
    };
}

test('fake short-circuits fetching and receives the gist url', function () {
    $received = null;

    GistClient::fake(function (GistUrl $url) use (&$received): GistFile {
        $received = $url;

        return new GistFile(filename: 'fake.php', language: 'PHP', content: '<?php');
    });

    $file = (new GistClient)->fetchFile(gistUrl('file-fake-php'));

    expect($file->filename)->toBe('fake.php')
        ->and($received?->id)->toBe('aa5a8f8cbc4f1e502dbb3ca546a4cbf3')
        ->and($received?->fragment)->toBe('file-fake-php');
});

test('clearFake restores the real fetching path', function () {
    GistClient::fake(fn (GistUrl $url): GistFile => new GistFile(filename: 'fake.php', language: 'PHP', content: '<?php'));
    GistClient::clearFake();

    $client = stubbedGistClient([
        'https://api.github.com/gists/aa5a8f8cbc4f1e502dbb3ca546a4cbf3' => json_encode([
            'files' => ['real.php' => ['filename' => 'real.php', 'language' => 'PHP', 'content' => '<?php // real']],
        ], JSON_THROW_ON_ERROR),
    ]);

    expect($client->fetchFile(gistUrl())->filename)->toBe('real.php');
});

test('fetches the selected php file from the gists api', function () {
    $client = stubbedGistClient([
        'https://api.github.com/gists/aa5a8f8cbc4f1e502dbb3ca546a4cbf3' => json_encode([
            'files' => [
                'README.md' => ['filename' => 'README.md', 'language' => 'Markdown', 'content' => '# Hi'],
                'script.php' => ['filename' => 'script.php', 'language' => 'PHP', 'content' => '<?php // hi'],
            ],
        ], JSON_THROW_ON_ERROR),
    ]);

    $file = $client->fetchFile(gistUrl());

    expect($file->filename)->toBe('script.php')
        ->and($file->content)->toBe('<?php // hi');
});

function respondingGistClient(int $status, string $body = ''): GistClient
{
    return new class($status, $body) extends GistClient
    {
        public function __construct(private readonly int $status, private readonly string $body) {}

        /** @return array{status: int, content: string} */
        protected function request(string $url): ?array
        {
            return ['status' => $this->status, 'content' => $this->body];
        }
    };
}

test('throws a friendly error on invalid json', function () {
    $client = stubbedGistClient([
        'https://api.github.com/gists/aa5a8f8cbc4f1e502dbb3ca546a4cbf3' => 'not-json',
    ]);

    $client->fetchFile(gistUrl());
})->throws(GistException::class, 'Unexpected response from the GitHub gists API.');

test('throws when the download fails', function () {
    stubbedGistClient([])->fetchFile(gistUrl());
})->throws(GistException::class, "Unable to download the gist from 'https://api.github.com/gists/aa5a8f8cbc4f1e502dbb3ca546a4cbf3'.");

test('throws a not found error when the gist does not exist', function () {
    respondingGistClient(404, '{"message":"Not Found"}')->fetchFile(gistUrl());
})->throws(GistException::class, 'The gist could not be found on GitHub.');

test('throws a rate limit error when github rejects the request', function (int $status) {
    respondingGistClient($status, '{"message":"API rate limit exceeded"}')->fetchFile(gistUrl());
})->with([
    'forbidden' => 403,
    'too many requests' => 429,
])->throws(GistException::class, 'GitHub rate limit exceeded while downloading the gist.');

test('throws a download error on other failure statuses', function () {
    respondingGistClient(500)->fetchFile(gistUrl());
})->throws(GistException::class, "Unable to download the gist from 'https://api.github.com/gists/aa5a8f8cbc4f1e502dbb3ca546a4cbf3'.");

test('sends the github token only to the api host', function () {
    $this->setEnvironmentVariable('GITHUB_TOKEN', 'secret-token');

    $client = new class extends GistClient
    {
        /** @return list<string> */
        public function headers(string $url): array
        {
            return $this->requestHeaders($url);
        }
    };

    expect($client->headers('https://api.github.com/gists/aa5a8f8cbc4f1e502dbb3ca546a4cbf3'))->toContain('Authorization: Bearer secret-token')
        ->and($client->headers('https://gist.githubusercontent.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3/raw/script.php'))->not->toContain('Authorization: Bearer secret-token');
});

test('omits the authorization header without a github token', function () {
    $this->setEnvironmentVariable('GITHUB_TOKEN', '');

    $client = new class extends GistClient
    {
        /** @return list<string> */
        public function headers(string $url): array
        {
            return $this->requestHeaders($url);
        }
    };

    expect(implode("\n", $client->headers('https://api.github.com/gists/aa5a8f8cbc4f1e502dbb3ca546a4cbf3')))->not->toContain('Authorization');
});

test('honors the configured timeout', function () {
    $server = stream_socket_server('tcp://127.0.0.1:0');
    assert(is_resource($server));

    $address = stream_socket_get_name($server, false);

    $client = new class(timeout: 1) extends GistClient
    {
        public function get(string $url): string
        {
            return $this->httpGet($url);
        }
    };

    $start = microtime(true);

    try {
        $client->get("http://{$address}/gists/aa5a8f8cbc4f1e502dbb3ca546a4cbf3");

        $this->fail('Expected the request to time out.');
    } catch (GistException) {
        expect(microtime(true) - $start)->toBeLessThan(5.0);
    } finally {
        fclose($server);
    }
});

test('downloads the raw file when the api content is truncated', function () {
    $rawUrl = 'https://gist.githubusercontent.com/WendellAdriel/aa5a8f8cbc4f1e502dbb3ca546a4cbf3/raw/script.php';

    $client = stubbedGistClient([
        'https://api.github.com/gists/aa5a8f8cbc4f1e502dbb3ca546a4cbf3' => json_encode([
            'files' => [
                'script.php' => [
                    'filename' => 'script.php',
                    'language' => 'PHP',
                    'content' => '<?php // partial',
                    'truncated' => true,
                    'raw_url' => $rawUrl,
                ],
            ],
        ], JSON_THROW_ON_ERROR),
        $rawUrl => '<?php // full content',
    ]);

    expect($client->fetchFile(gistUrl())->content)->toBe('<?php // full content');
});
