<?php

declare(strict_types=1);

namespace Cpx\Exceptions;

use Cpx\Gists\GistFile;
use Exception;

use function Laravel\Prompts\callout;

class GistException extends Exception
{
    /** @param list<string> $details */
    public function __construct(
        string $message,
        private readonly string $label = 'Gist error',
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function render(): void
    {
        callout(
            label: $this->label,
            content: [$this->getMessage(), ...$this->details],
            type: 'error',
        );
    }

    public static function downloadFailed(string $url, ?int $status = null): self
    {
        return new self(
            "Unable to download the gist from '{$url}'.",
            'Gist download failed',
            $status === null ? [] : ["GitHub responded with HTTP status {$status}."],
        );
    }

    public static function gistNotFound(): self
    {
        return new self('The gist could not be found on GitHub.', 'Gist not found', [
            'Check that the gist still exists and the id in the URL is correct.',
        ]);
    }

    public static function rateLimited(): self
    {
        return new self('GitHub rate limit exceeded while downloading the gist.', 'GitHub rate limit', [
            'Set the GITHUB_TOKEN environment variable to authenticate and raise the limit.',
        ]);
    }

    public static function invalidResponse(): self
    {
        return new self('Unexpected response from the GitHub gists API.', 'Gist download failed');
    }

    public static function notPhpGist(): self
    {
        return new self('The gist does not contain a PHP file.', 'Gist is not runnable');
    }

    /** @param list<GistFile> $files */
    public static function ambiguousPhpFiles(array $files): self
    {
        $filenames = implode(', ', array_map(fn (GistFile $file): string => $file->filename, $files));
        $fragment = $files[0]->fragment();

        return new self(
            "The gist contains multiple PHP files ({$filenames}).",
            'Multiple PHP files found',
            ["Append a fragment like '#{$fragment}' to the gist URL to pick one."],
        );
    }

    public static function unknownFragment(string $fragment): self
    {
        return new self("No gist file matches '#{$fragment}'.", 'Gist file not found');
    }

    public static function notPhpFile(GistFile $file): self
    {
        return new self("The gist file '{$file->filename}' is not a PHP script.", 'Gist is not runnable');
    }
}
