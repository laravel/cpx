<?php

declare(strict_types=1);

namespace Cpx\Cache;

class ExecSandboxMetadata
{
    /**
     * @param  list<string>  $packages
     */
    public function __construct(
        public readonly string $key,
        public array $packages = [],
        public ?int $lastUpdatedAt = null,
        public ?int $lastRunAt = null,
    ) {
        //
    }

    public static function rootPath(): string
    {
        return cpx_path('.exec_cache');
    }

    public static function pathFor(string $key): string
    {
        return cpx_path(".exec_cache/{$key}");
    }

    public function path(): string
    {
        return self::pathFor($this->key);
    }
}
