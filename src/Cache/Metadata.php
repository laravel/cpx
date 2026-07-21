<?php

declare(strict_types=1);

namespace Cpx\Cache;

use Closure;
use Cpx\Packages\Package;
use Cpx\Support\Arr;
use Cpx\Support\Filesystem;
use Cpx\Support\Lock;
use InvalidArgumentException;

class Metadata
{
    public const UPDATE_CHECK_INTERVAL = 60 * 60;

    private const FILE = '.cpx_metadata.json';

    private const LOCK_FILE = '.cpx_metadata.lock';

    private const VERSION = 2;

    /**
     * @param  array<string, PackageMetadata>  $packages
     * @param  array<string, ExecSandboxMetadata>  $execCache
     */
    protected function __construct(
        public array $packages = [],
        public array $execCache = [],
    ) {
        //
    }

    public static function open(): self
    {
        $metadataFile = cpx_path(self::FILE);

        if (! file_exists($metadataFile)) {
            return new self;
        }

        $contents = file_get_contents($metadataFile);
        $json = $contents === false ? [] : json_decode($contents, true);

        if (! is_array($json)) {
            $json = [];
        }

        return new self(
            packages: Arr::mapWithKeys(
                function (string $key, mixed $value): array {
                    if (! is_array($value)) {
                        return [];
                    }

                    try {
                        $package = Package::parse($key);
                    } catch (InvalidArgumentException) {
                        return [];
                    }

                    return [
                        $key => new PackageMetadata(
                            package: $package,
                            lastUpdatedAt: self::normalizeTimestamp($value['last_updated'] ?? null),
                            lastRunAt: self::normalizeTimestamp($value['last_run'] ?? null),
                        ),
                    ];
                },
                is_array($json['packages'] ?? null) ? $json['packages'] : [],
            ),
            execCache: Arr::mapWithKeys(
                function (string $key, mixed $value): array {
                    if (! is_array($value)) {
                        return [];
                    }

                    return [
                        $key => new ExecSandboxMetadata(
                            key: $key,
                            packages: is_array($value['packages'] ?? null) ? array_values($value['packages']) : [],
                            lastUpdatedAt: self::normalizeTimestamp($value['last_updated'] ?? null),
                            lastRunAt: self::normalizeTimestamp($value['last_run'] ?? null),
                        ),
                    ];
                },
                is_array($json['execCache'] ?? null) ? $json['execCache'] : [],
            ),
        );
    }

    /**
     * @template TReturn
     *
     * @param  Closure(self): TReturn  $mutator
     * @return TReturn
     */
    public static function transaction(Closure $mutator): mixed
    {
        return Lock::run(cpx_path(self::LOCK_FILE), function () use ($mutator) {
            $metadata = self::open();
            $result = $mutator($metadata);
            $metadata->writeToDisk();

            return $result;
        });
    }

    public function recordRun(Package $package): self
    {
        $this->forPackage($package)->lastRunAt = time();

        return $this;
    }

    public function recordUpdate(Package $package): self
    {
        $this->forPackage($package)->lastUpdatedAt = time();

        return $this;
    }

    public function hasPackage(string|Package $package): bool
    {
        if ($package instanceof Package) {
            $package = $package->fullPackageString();
        }

        return array_key_exists($package, $this->packages);
    }

    /**
     * @return array{
     *     version: int,
     *     packages: array<string, array{last_updated: int|null, last_run: int|null}>,
     *     execCache: array<string, array{packages: list<string>, last_updated: int|null, last_run: int|null}>
     * }
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'packages' => Arr::mapWithKeys(
                fn (string $key, PackageMetadata $packageMetadata): array => [
                    $packageMetadata->package->fullPackageString() => [
                        'last_updated' => $packageMetadata->lastUpdatedAt,
                        'last_run' => $packageMetadata->lastRunAt,
                    ],
                ],
                $this->packages,
            ),
            'execCache' => Arr::mapWithKeys(
                fn (string $key, ExecSandboxMetadata $sandbox): array => [
                    $sandbox->key => [
                        'packages' => $sandbox->packages,
                        'last_updated' => $sandbox->lastUpdatedAt,
                        'last_run' => $sandbox->lastRunAt,
                    ],
                ],
                $this->execCache,
            ),
        ];
    }

    private function writeToDisk(): void
    {
        Filesystem::writeAtomic(cpx_path(self::FILE), (string) json_encode($this->toArray(), JSON_PRETTY_PRINT));
    }

    private function forPackage(Package $package): PackageMetadata
    {
        return $this->packages[$package->fullPackageString()] ??= new PackageMetadata($package);
    }

    private static function normalizeTimestamp(int|string|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : $timestamp;
    }
}
