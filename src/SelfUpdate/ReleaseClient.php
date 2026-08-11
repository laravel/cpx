<?php

declare(strict_types=1);

namespace Cpx\SelfUpdate;

use Closure;
use Cpx\Exceptions\SelfUpdateException;
use RuntimeException;

class ReleaseClient
{
    public const LATEST_RELEASE_ENDPOINT = 'https://api.github.com/repos/laravel/cpx/releases/latest';

    public const ASSET_NAME = 'cpx';

    private static ?Closure $fakeLatest = null;

    private static ?Closure $fakeDownload = null;

    public function __construct(private int $timeout = 10)
    {
        //
    }

    /** @throws SelfUpdateException */
    public function latest(): Release
    {
        if (self::$fakeLatest !== null) {
            return (self::$fakeLatest)();
        }

        $payload = json_decode($this->httpGet(self::LATEST_RELEASE_ENDPOINT), true);

        if (! is_array($payload) || ! is_string($payload['tag_name'] ?? null) || $payload['tag_name'] === '') {
            throw SelfUpdateException::invalidResponse();
        }

        [$downloadUrl, $sha256] = $this->pharAsset($payload, $payload['tag_name']);

        return new Release($payload['tag_name'], $downloadUrl, $sha256);
    }

    /** @throws SelfUpdateException */
    public function download(Release $release, string $destination): void
    {
        if (self::$fakeDownload !== null) {
            (self::$fakeDownload)($release, $destination);

            return;
        }

        if (self::$fakeLatest !== null) {
            throw new RuntimeException('ReleaseClient::fake() requires a download handler when download() is used.');
        }

        $content = $this->httpGet($release->downloadUrl);

        if (@file_put_contents($destination, $content) !== strlen($content)) {
            throw SelfUpdateException::unwritableTemporaryFile($destination);
        }
    }

    /**
     * @param  callable(): Release  $latest
     * @param  (callable(Release, string): void)|null  $download
     */
    public static function fake(callable $latest, ?callable $download = null): void
    {
        self::$fakeLatest = $latest(...);
        self::$fakeDownload = $download === null ? null : $download(...);
    }

    public static function clearFake(): void
    {
        self::$fakeLatest = null;
        self::$fakeDownload = null;
    }

    /** @throws SelfUpdateException */
    protected function httpGet(string $url): string
    {
        $response = $this->request($url);

        if ($response === null) {
            throw SelfUpdateException::releaseFetchFailed($url);
        }

        $status = $response['status'];

        return match (true) {
            $status >= 200 && $status < 300 => $response['content'],
            $status === 401 => throw SelfUpdateException::invalidToken(),
            $status === 403, $status === 429 => throw SelfUpdateException::rateLimited(),
            default => throw SelfUpdateException::releaseFetchFailed($url, $status),
        };
    }

    /** @return array{status: int, content: string}|null */
    protected function request(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $this->requestHeaders($url)),
                'timeout' => $this->timeout,
                'ignore_errors' => true,
                // The wrapper resends all headers on redirects, so token-bearing API requests must not follow them.
                'follow_location' => $this->isApiRequest($url) ? 0 : 1,
            ],
        ]);

        $stream = @fopen($url, 'r', false, $context);

        if ($stream === false) {
            return null;
        }

        $body = stream_get_contents($stream);
        $headers = stream_get_meta_data($stream)['wrapper_data'] ?? [];

        fclose($stream);

        if ($body === false) {
            return null;
        }

        return [
            'status' => $this->statusCode(is_array($headers) ? $headers : []),
            'content' => $body,
        ];
    }

    /** @return list<string> */
    protected function requestHeaders(string $url): array
    {
        $headers = ['User-Agent: cpx', 'Accept: application/vnd.github+json'];

        $token = $_SERVER['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN');

        if (is_string($token) && $token !== '' && $this->isApiRequest($url)) {
            $headers[] = "Authorization: Bearer {$token}";
        }

        return $headers;
    }

    private function isApiRequest(string $url): bool
    {
        return str_starts_with($url, 'https://api.github.com/');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string|null}
     *
     * @throws SelfUpdateException
     */
    private function pharAsset(array $payload, string $tag): array
    {
        $assets = is_array($payload['assets'] ?? null) ? $payload['assets'] : [];

        foreach ($assets as $asset) {
            if (! is_array($asset) || ($asset['name'] ?? null) !== self::ASSET_NAME) {
                continue;
            }

            $downloadUrl = $asset['browser_download_url'] ?? null;

            if (is_string($downloadUrl) && $downloadUrl !== '') {
                return [$downloadUrl, $this->sha256Digest($asset)];
            }
        }

        throw SelfUpdateException::assetNotFound($tag);
    }

    /** @param array<string, mixed> $asset */
    private function sha256Digest(array $asset): ?string
    {
        $digest = $asset['digest'] ?? null;

        return is_string($digest) && str_starts_with($digest, 'sha256:')
            ? substr($digest, strlen('sha256:'))
            : null;
    }

    /** @param array<int, mixed> $headers */
    private function statusCode(array $headers): int
    {
        $status = 0;

        // Redirects prepend earlier responses, so the last status line wins.
        foreach ($headers as $header) {
            if (is_string($header) && preg_match('~\AHTTP/\S+\s+(\d{3})~', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        return $status;
    }
}
