<?php

declare(strict_types=1);

namespace Cpx\Gists;

use Closure;
use Cpx\Exceptions\GistException;

class GistClient
{
    private static ?Closure $fakeFetcher = null;

    public function __construct(private int $timeout = 10)
    {
        //
    }

    /** @throws GistException */
    public function fetchFile(GistUrl $url): GistFile
    {
        if (self::$fakeFetcher !== null) {
            return (self::$fakeFetcher)($url);
        }

        $endpoint = "https://api.github.com/gists/{$url->id}";

        if ($url->revision !== null) {
            $endpoint .= "/{$url->revision}";
        }

        $payload = json_decode($this->httpGet($endpoint), true);

        if (! is_array($payload)) {
            throw GistException::invalidResponse();
        }

        $file = Gist::fromApi($payload)->select($url->fragment);

        if (! $file->truncated || $file->rawUrl === null) {
            return $file;
        }

        return new GistFile(
            filename: $file->filename,
            language: $file->language,
            content: $this->httpGet($file->rawUrl),
            rawUrl: $file->rawUrl,
        );
    }

    /**
     * @param  callable(GistUrl): GistFile  $fetcher
     */
    public static function fake(callable $fetcher): void
    {
        self::$fakeFetcher = $fetcher(...);
    }

    public static function clearFake(): void
    {
        self::$fakeFetcher = null;
    }

    /** @throws GistException */
    protected function httpGet(string $url): string
    {
        $response = $this->request($url);

        if ($response === null) {
            throw GistException::downloadFailed($url);
        }

        $status = $response['status'];

        return match (true) {
            $status >= 200 && $status < 300 => $response['content'],
            $status === 404 => throw GistException::gistNotFound(),
            $status === 403, $status === 429 => throw GistException::rateLimited(),
            default => throw GistException::downloadFailed($url, $status),
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

        if (is_string($token) && $token !== '' && str_starts_with($url, 'https://api.github.com/')) {
            $headers[] = "Authorization: Bearer {$token}";
        }

        return $headers;
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
