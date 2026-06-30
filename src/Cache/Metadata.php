<?php

declare(strict_types=1);

namespace Cpx\Cache;

use Closure;
use Cpx\Packages\Package;
use Cpx\Support\Arr;
use Cpx\Support\Filesystem;
use Cpx\Support\Lock;

class Metadata
{
    public const UPDATE_CHECK_INTERVAL = 60 * 60;

    private const FILE = '.cpx_metadata.json';

    private const LOCK_FILE = '.cpx_metadata.lock';

    private const VERSION = 2;

    /**
     * @param  array<string, PackageMetadata>  $packages
     * @param  array<string, ExecSandboxMetadata>  $execCache
     * @param  array<string, mixed>  $aliases
     */
    protected function __construct(
        public array $packages = [],
        public array $execCache = [],
        public array $aliases = [],
    ) {}

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
                fn (string $key, array $value): array => [
                    $key => new PackageMetadata(
                        package: Package::parse($key),
                        lastUpdatedAt: $value['last_updated'] ?? null,
                        lastRunAt: $value['last_run'] ?? null,
                    ),
                ],
                is_array($json['packages'] ?? null) ? $json['packages'] : [],
            ),
            execCache: Arr::mapWithKeys(
                fn (string $key, array $value): array => [
                    $key => new ExecSandboxMetadata(
                        key: $key,
                        packages: is_array($value['packages'] ?? null) ? array_values($value['packages']) : [],
                        lastUpdatedAt: $value['last_updated'] ?? null,
                        lastRunAt: $value['last_run'] ?? null,
                    ),
                ],
                is_array($json['execCache'] ?? null) ? $json['execCache'] : [],
            ),
            aliases: is_array($json['aliases'] ?? null) ? $json['aliases'] : [],
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
        $this->forPackage($package)->lastRunAt = date('Y-m-d H:i:s');

        return $this;
    }

    public function recordUpdate(Package $package): self
    {
        $this->forPackage($package)->lastUpdatedAt = date('Y-m-d H:i:s');

        return $this;
    }

    public function save(): void
    {
        Lock::run(cpx_path(self::LOCK_FILE), function (): void {
            $this->writeToDisk();
        });
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
     *     aliases: array<string, mixed>,
     *     packages: array<string, array{last_updated: string|null, last_run: string|null}>,
     *     execCache: array<string, array{packages: list<string>, last_updated: int|null, last_run: int|null}>
     * }
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'aliases' => $this->aliases,
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
}
