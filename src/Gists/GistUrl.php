<?php

declare(strict_types=1);

namespace Cpx\Gists;

readonly class GistUrl
{
    private const PATTERN = '~\A(?:https?://)?gist\.github\.com/(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?/)?([0-9a-f]{20}|[0-9a-f]{32})/?(?:\#(.+))?\z~';

    private function __construct(
        public string $id,
        public ?string $fragment = null,
    ) {}

    public static function tryFrom(string $target): ?self
    {
        if (preg_match(self::PATTERN, $target, $matches) !== 1) {
            return null;
        }

        return new self($matches[1], $matches[2] ?? null);
    }
}
