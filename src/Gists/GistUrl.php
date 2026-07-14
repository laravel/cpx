<?php

declare(strict_types=1);

namespace Cpx\Gists;

readonly class GistUrl
{
    private const PATTERN = '~\A(?:https?://)?gist\.github\.com/(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?/)?([0-9a-f]{20}|[0-9a-f]{32})(?:/([0-9a-f]{40}))?/?(?:\#(.+))?\z~';

    private const RAW_PATTERN = '~\A(?:https?://)?(gist\.githubusercontent\.com/[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?/([0-9a-f]{20}|[0-9a-f]{32})/raw/(?:[0-9a-f]{40}/)?([^#?/]+))\z~';

    private const URL_PATTERN = '~\A(?:[a-z][a-z0-9+.-]*://|gist\.github(?:usercontent)?\.com/)~i';

    private function __construct(
        public string $id,
        public ?string $revision = null,
        public ?string $fragment = null,
        public ?string $rawUrl = null,
        public ?string $filename = null,
    ) {}

    public static function tryFrom(string $target): ?self
    {
        if (preg_match(self::PATTERN, $target, $matches) === 1) {
            return new self(
                $matches[1],
                ($matches[2] ?? '') === '' ? null : $matches[2],
                ($matches[3] ?? '') === '' ? null : $matches[3],
            );
        }

        if (preg_match(self::RAW_PATTERN, $target, $matches) === 1) {
            return new self(
                $matches[2],
                rawUrl: "https://{$matches[1]}",
                filename: rawurldecode($matches[3]),
            );
        }

        return null;
    }

    public static function isUrl(string $target): bool
    {
        return preg_match(self::URL_PATTERN, $target) === 1;
    }
}
