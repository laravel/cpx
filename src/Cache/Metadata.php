<?php

declare(strict_types=1);

namespace Cpx\Cache;

use Cpx\Packages\Package;
use Cpx\Support\Arr;

class Metadata
{
    private const FILE = '.cpx_metadata.json';

    /**
     * @param  array<string, PackageMetadata>  $packages
     * @param  array<string, array{packages?: list<string>, last_updated?: int, last_run?: int}>  $execCache
     */
    protected function __construct(
        public array $packages = [],
        public array $execCache = [],
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
            execCache: is_array($json['execCache'] ?? null) ? $json['execCache'] : [],
        );
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
        $metadataFile = cpx_path(self::FILE);

        if (! is_dir(dirname($metadataFile))) {
            mkdir(dirname($metadataFile), 0755, true);
        }

        file_put_contents($metadataFile, json_encode($this->toArray(), JSON_PRETTY_PRINT));
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
     *     packages: array<string, array{last_updated: string|null, last_run: string|null}>,
     *     execCache: array<string, array{packages?: list<string>, last_updated?: int, last_run?: int}>
     * }
     */
    public function toArray(): array
    {
        return [
            'packages' => Arr::mapWithKeys(
                fn (string $key, PackageMetadata $packageMetadata): array => [
                    $packageMetadata->package->fullPackageString() => [
                        'last_updated' => $packageMetadata->lastUpdatedAt,
                        'last_run' => $packageMetadata->lastRunAt,
                    ],
                ],
                $this->packages,
            ),
            'execCache' => $this->execCache,
        ];
    }

    private function forPackage(Package $package): PackageMetadata
    {
        return $this->packages[$package->fullPackageString()] ??= new PackageMetadata($package);
    }
}
