<?php

namespace Cpx;

class Metadata
{
    /** @param array<string,PackageMetadata> $packages */
    protected function __construct(
        public array $packages = [],
        public array $execCache = [],
    ) {}

    public static function open(): Metadata
    {
        $metadataFile = cpx_path('.cpx_metadata.json');

        if (file_exists($metadataFile)) {
            $json = json_decode(file_get_contents($metadataFile), true);

            return new Metadata(
                packages: Utils::arrayMapAssoc(
                    fn ($key, $value) => [
                        $key => new PackageMetadata(
                            package: Package::parse($key),
                            lastUpdatedAt: $value['last_updated'] ?? null,
                            lastRunAt: $value['last_run'] ?? null,
                        ),
                    ],
                    $json['packages'] ?? [],
                ),
                execCache: $json['execCache'] ?? [],
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
