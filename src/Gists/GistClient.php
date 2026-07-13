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

        $payload = json_decode($this->httpGet("https://api.github.com/gists/{$url->id}"), true);

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
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: cpx\r\nAccept: application/vnd.github+json",
                'timeout' => $this->timeout,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw GistException::downloadFailed($url);
        }

        return $response;
    }
}
