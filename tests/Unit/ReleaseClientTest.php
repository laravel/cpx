<?php

declare(strict_types=1);

use Cpx\Exceptions\SelfUpdateException;
use Cpx\SelfUpdate\Release;
use Cpx\SelfUpdate\ReleaseClient;

/**
 * @param  list<array<string, mixed>>  $assets
 */
function latestReleasePayload(string $tag = 'v2.1.0', ?array $assets = null): string
{
    $assets ??= [
        ['name' => 'checksums.txt', 'browser_download_url' => 'https://github.com/laravel/cpx/releases/download/v2.1.0/checksums.txt'],
        [
            'name' => 'cpx',
            'browser_download_url' => "https://github.com/laravel/cpx/releases/download/{$tag}/cpx",
            'digest' => 'sha256:'.str_repeat('ab', 32),
        ],
    ];

    return json_encode(['tag_name' => $tag, 'assets' => $assets], JSON_THROW_ON_ERROR);
}

/** @param array<string, string> $responses */
function stubbedReleaseClient(array $responses): ReleaseClient
{
    return new class($responses) extends ReleaseClient
    {
        /** @param array<string, string> $responses */
        public function __construct(private readonly array $responses) {}

        protected function httpGet(string $url): string
        {
            if (! array_key_exists($url, $this->responses)) {
                throw SelfUpdateException::releaseFetchFailed($url);
            }

            return $this->responses[$url];
        }
    };
}

function respondingReleaseClient(int $status, string $body = ''): ReleaseClient
{
    return new class($status, $body) extends ReleaseClient
    {
        public function __construct(private readonly int $status, private readonly string $body) {}

        /** @return array{status: int, content: string} */
        protected function request(string $url): ?array
        {
            return ['status' => $this->status, 'content' => $this->body];
        }
    };
}

test('latest parses the tag, download url, and sha256 digest', function () {
    $client = stubbedReleaseClient([
        ReleaseClient::LATEST_RELEASE_ENDPOINT => latestReleasePayload(),
    ]);

    $release = $client->latest();

    expect($release->tag)->toBe('v2.1.0')
        ->and($release->downloadUrl)->toBe('https://github.com/laravel/cpx/releases/download/v2.1.0/cpx')
        ->and($release->sha256)->toBe(str_repeat('ab', 32));
});

test('latest handles an asset without a sha256 digest', function () {
    $client = stubbedReleaseClient([
        ReleaseClient::LATEST_RELEASE_ENDPOINT => latestReleasePayload(assets: [
            ['name' => 'cpx', 'browser_download_url' => 'https://github.com/laravel/cpx/releases/download/v2.1.0/cpx'],
        ]),
    ]);

    expect($client->latest()->sha256)->toBeNull();
});

test('latest ignores digests that are not sha256', function () {
    $client = stubbedReleaseClient([
        ReleaseClient::LATEST_RELEASE_ENDPOINT => latestReleasePayload(assets: [
            [
                'name' => 'cpx',
                'browser_download_url' => 'https://github.com/laravel/cpx/releases/download/v2.1.0/cpx',
                'digest' => 'sha512:'.str_repeat('cd', 64),
            ],
        ]),
    ]);

    expect($client->latest()->sha256)->toBeNull();
});

test('latest throws on a malformed response', function (string $body) {
    stubbedReleaseClient([ReleaseClient::LATEST_RELEASE_ENDPOINT => $body])->latest();
})->with([
    'not json' => 'not-json',
    'missing tag' => '{"assets": []}',
    'empty tag' => '{"tag_name": "", "assets": []}',
])->throws(SelfUpdateException::class, 'Unexpected response from the GitHub releases API.');

test('latest treats a redirect as a failure instead of following it', function () {
    respondingReleaseClient(302)->latest();
})->throws(SelfUpdateException::class, "Unable to fetch the latest cpx release from '".ReleaseClient::LATEST_RELEASE_ENDPOINT."'.");

test('latest throws when the release has no cpx phar asset', function () {
    $client = stubbedReleaseClient([
        ReleaseClient::LATEST_RELEASE_ENDPOINT => latestReleasePayload(assets: [
            ['name' => 'checksums.txt', 'browser_download_url' => 'https://github.com/laravel/cpx/releases/download/v2.1.0/checksums.txt'],
        ]),
    ]);

    $client->latest();
})->throws(SelfUpdateException::class, 'The latest release (v2.1.0) does not include a cpx PHAR asset.');

test('latest throws when the connection fails', function () {
    $client = new class extends ReleaseClient
    {
        protected function request(string $url): ?array
        {
            return null;
        }
    };

    $client->latest();
})->throws(SelfUpdateException::class, "Unable to fetch the latest cpx release from '".ReleaseClient::LATEST_RELEASE_ENDPOINT."'.");

test('latest throws an invalid token error when authentication fails', function () {
    respondingReleaseClient(401, '{"message":"Bad credentials"}')->latest();
})->throws(SelfUpdateException::class, 'GitHub rejected the provided GITHUB_TOKEN.');

test('latest throws a rate limit error when github rejects the request', function (int $status) {
    respondingReleaseClient($status, '{"message":"API rate limit exceeded"}')->latest();
})->with([
    'forbidden' => 403,
    'too many requests' => 429,
])->throws(SelfUpdateException::class, 'GitHub rate limit exceeded while checking for a new cpx version.');

test('latest throws a fetch error on other failure statuses', function () {
    respondingReleaseClient(500)->latest();
})->throws(SelfUpdateException::class, "Unable to fetch the latest cpx release from '".ReleaseClient::LATEST_RELEASE_ENDPOINT."'.");

test('download writes the phar to the destination', function () {
    $release = new Release('v2.1.0', 'https://github.com/laravel/cpx/releases/download/v2.1.0/cpx');
    $client = stubbedReleaseClient([$release->downloadUrl => 'phar-bytes']);
    $destination = $this->temporaryDirectory().'/cpx.tmp';

    $client->download($release, $destination);

    expect(file_get_contents($destination))->toBe('phar-bytes');
});

test('download throws when the destination is not writable', function () {
    $release = new Release('v2.1.0', 'https://github.com/laravel/cpx/releases/download/v2.1.0/cpx');
    $client = stubbedReleaseClient([$release->downloadUrl => 'phar-bytes']);
    $destination = $this->temporaryDirectory().'/missing-directory/cpx.tmp';

    $client->download($release, $destination);
})->throws(SelfUpdateException::class, 'Unable to write the downloaded PHAR');

test('sends the github token only to the api host', function () {
    $this->setEnvironmentVariable('GITHUB_TOKEN', 'secret-token');

    $client = new class extends ReleaseClient
    {
        /** @return list<string> */
        public function headers(string $url): array
        {
            return $this->requestHeaders($url);
        }
    };

    expect($client->headers(ReleaseClient::LATEST_RELEASE_ENDPOINT))->toContain('Authorization: Bearer secret-token')
        ->and($client->headers('https://github.com/laravel/cpx/releases/download/v2.1.0/cpx'))->not->toContain('Authorization: Bearer secret-token')
        ->and($client->headers(ReleaseClient::LATEST_RELEASE_ENDPOINT))->toContain('User-Agent: cpx')
        ->and($client->headers(ReleaseClient::LATEST_RELEASE_ENDPOINT))->toContain('Accept: application/vnd.github+json');
});

test('fake short-circuits the release lookup and download', function () {
    $downloaded = null;

    ReleaseClient::fake(
        latest: fn (): Release => new Release('v9.9.9', 'https://github.com/laravel/cpx/releases/download/v9.9.9/cpx'),
        download: function (Release $release, string $destination) use (&$downloaded): void {
            $downloaded = "{$release->tag} -> {$destination}";
        },
    );

    $client = new ReleaseClient;
    $release = $client->latest();
    $client->download($release, '/tmp/cpx.tmp');

    expect($release->tag)->toBe('v9.9.9')
        ->and($downloaded)->toBe('v9.9.9 -> /tmp/cpx.tmp');
});

test('clearFake restores the real lookup path', function () {
    ReleaseClient::fake(fn (): Release => new Release('v9.9.9', 'https://github.com/laravel/cpx/releases/download/v9.9.9/cpx'));
    ReleaseClient::clearFake();

    $client = stubbedReleaseClient([ReleaseClient::LATEST_RELEASE_ENDPOINT => latestReleasePayload()]);

    expect($client->latest()->tag)->toBe('v2.1.0');
});
