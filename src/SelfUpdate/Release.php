<?php

declare(strict_types=1);

namespace Cpx\SelfUpdate;

readonly class Release
{
    public function __construct(
        public string $tag,
        public string $downloadUrl,
        public ?string $sha256 = null,
    ) {
        //
    }
}
