<?php

namespace Cpx;

class Metadata
{
    /**
     * @param  array<string, PackageMetadata>  $packages
     * @param  array<string, array{packages?: list<string>, last_updated?: int, last_run?: int}>  $execCache
     */
    protected function __construct(
        public array $packages = [],
        public array $execCache = [],
    ) {}

    public static function open(): Metadata
    {
        $metadataFile = cpx_path('.cpx_metadata.json');

        if (file_exists($metadataFile)) {
            $contents = file_get_contents($metadataFile);
            $json = $contents === false ? [] : json_decode($contents, true);

            if (! is_array($json)) {
                $json = [];
            }

            return new Metadata(
                packages: Utils::arrayMapAssoc(
                    fn ($key, $value) => [
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

        return new Metadata;
    }

    public function updateLastCheckTime(Package $package, string $type = 'run'): Metadata
    {
        $packageKey = $package->fullPackageString();
        $currentTime = date('Y-m-d H:i:s');

        if (! isset($this->packages[$packageKey])) {
            $this->packages[$packageKey] = new PackageMetadata($package);
        }

        if ($type === 'run') {
            $this->packages[$packageKey]->lastRunAt = $currentTime;
        } else {
            $this->packages[$packageKey]->lastUpdatedAt = $currentTime;
        }

        return $this;
    }

    public function save(): void
    {
        $metadataFile = cpx_path('.cpx_metadata.json');
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
            'packages' => Utils::arrayMapAssoc(
                fn ($key, PackageMetadata $packageMetadata) => [
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
}
