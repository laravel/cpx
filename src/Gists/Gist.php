<?php

declare(strict_types=1);

namespace Cpx\Gists;

use Closure;
use Cpx\Exceptions\GistException;

readonly class Gist
{
    /** @param list<GistFile> $files */
    private function __construct(public array $files)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws GistException When the payload does not look like a gists API response.
     */
    public static function fromApi(array $payload): self
    {
        $entries = $payload['files'] ?? null;

        if (! is_array($entries) || $entries === []) {
            throw GistException::invalidResponse();
        }

        $files = [];

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! is_string($entry['filename'] ?? null)) {
                throw GistException::invalidResponse();
            }

            $content = $entry['content'] ?? null;
            $truncated = ($entry['truncated'] ?? false) === true;
            $rawUrl = is_string($entry['raw_url'] ?? null) ? $entry['raw_url'] : null;

            if (! is_string($content) && ! $truncated) {
                throw GistException::invalidResponse();
            }

            if ($truncated && $rawUrl === null) {
                throw GistException::invalidResponse();
            }

            $files[] = new GistFile(
                filename: $entry['filename'],
                language: is_string($entry['language'] ?? null) ? $entry['language'] : null,
                content: is_string($content) ? $content : '',
                truncated: $truncated,
                rawUrl: $rawUrl,
            );
        }

        return new self($files);
    }

    /**
     * @param  (Closure(GistFile...): GistFile)|null  $choose
     *
     * @throws GistException
     */
    public function select(?string $fragment, ?Closure $choose = null): GistFile
    {
        if ($fragment !== null) {
            return $this->selectByFragment($fragment);
        }

        $phpFiles = array_values(array_filter($this->files, fn (GistFile $file): bool => $file->isPhp()));

        if ($phpFiles === []) {
            $phpFiles = array_values(array_filter($this->files, fn (GistFile $file): bool => $file->hasPhpTag()));
        }

        if ($phpFiles === []) {
            throw GistException::notPhpGist();
        }

        if (count($phpFiles) === 1) {
            return $phpFiles[0];
        }

        if ($choose !== null) {
            return $choose(...$phpFiles);
        }

        throw GistException::ambiguousPhpFiles($phpFiles);
    }

    private function selectByFragment(string $fragment): GistFile
    {
        $normalized = GistFile::slug($fragment);

        foreach ($this->files as $file) {
            if ($file->fragment() !== $normalized) {
                continue;
            }

            if (! $file->isPhp() && ! $file->hasPhpTag()) {
                throw GistException::notPhpFile($file);
            }

            return $file;
        }

        throw GistException::unknownFragment($fragment);
    }
}
