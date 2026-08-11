<?php

declare(strict_types=1);

namespace Cpx\Exceptions;

use Exception;

use function Laravel\Prompts\callout;

class SelfUpdateException extends Exception
{
    /** @param list<string> $details */
    public function __construct(
        string $message,
        private readonly string $label = 'Self-update failed',
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

    public static function notRunningFromPhar(): self
    {
        return new self('self-update is only available when cpx is running as a PHAR.', 'Self-update unavailable', [
            'Update a Composer-managed installation with:',
            'composer global update cpx/cpx',
        ]);
    }

    public static function releaseFetchFailed(string $url, ?int $status = null): self
    {
        return new self(
            "Unable to fetch the latest cpx release from '{$url}'.",
            'Self-update failed',
            $status === null ? [] : ["GitHub responded with HTTP status {$status}."],
        );
    }

    public static function invalidToken(): self
    {
        return new self('GitHub rejected the provided GITHUB_TOKEN.', 'GitHub authentication failed', [
            'Check that the GITHUB_TOKEN environment variable holds a valid token.',
        ]);
    }

    public static function rateLimited(): self
    {
        return new self('GitHub rate limit exceeded while checking for a new cpx version.', 'GitHub rate limit', [
            'Set the GITHUB_TOKEN environment variable to authenticate and raise the limit.',
        ]);
    }

    public static function invalidResponse(): self
    {
        return new self('Unexpected response from the GitHub releases API.');
    }

    public static function assetNotFound(string $tag): self
    {
        return new self("The latest release ({$tag}) does not include a cpx PHAR asset.", 'Self-update failed', [
            'Download cpx manually from https://github.com/laravel/cpx/releases.',
        ]);
    }

    public static function unwritableTemporaryFile(string $file): self
    {
        return new self("Unable to write the downloaded PHAR to '{$file}'.");
    }

    public static function checksumMismatch(): self
    {
        return new self('The downloaded PHAR does not match the sha256 checksum GitHub published.', 'Self-update failed', [
            "Run 'cpx self-update' again to retry the download.",
        ]);
    }

    public static function validationFailed(string $tag): self
    {
        return new self("The downloaded PHAR did not report version {$tag} when executed.", 'Self-update failed', [
            "Run 'cpx self-update' again to retry the download.",
        ]);
    }

    public static function notWritable(string $path): self
    {
        return new self("The cpx PHAR at '{$path}' cannot be replaced.", 'Self-update failed', [
            'Check the file permissions or rerun the command with elevated privileges.',
        ]);
    }

    public static function swapFailed(string $path, ?string $backup = null): self
    {
        return new self("Unable to replace the cpx PHAR at '{$path}'.", 'Self-update failed', $backup === null ? [
            'The previous PHAR was left in place.',
        ] : [
            'The previous PHAR could not be restored automatically.',
            "A backup was kept at '{$backup}'.",
        ]);
    }
}
