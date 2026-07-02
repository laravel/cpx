<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Cpx\Support\Filesystem;

class UserAliases
{
    private const FILE = 'aliases.json';

    /**
     * @param  array<string, Package>  $aliases
     */
    protected function __construct(
        protected array $aliases = [],
    ) {
        //
    }

    public static function open(): self
    {
        $file = cpx_path(self::FILE);

        if (! file_exists($file)) {
            return new self;
        }

        $json = json_decode((string) file_get_contents($file), true);

        return new self(array_map(
            fn (string $value): Package => Package::parse($value),
            $json,
        ));
    }

    /** @return array<string, Package> */
    public function all(): array
    {
        return $this->aliases;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->aliases);
    }

    public function find(string $name): ?Package
    {
        return $this->aliases[$name] ?? null;
    }

    public function put(string $name, Package $package): self
    {
        $this->aliases[$name] = $package;

        return $this;
    }

    public function forget(string $name): self
    {
        unset($this->aliases[$name]);

        return $this;
    }

    public function save(): void
    {
        Filesystem::writeAtomic(cpx_path(self::FILE), (string) json_encode($this->toArray(), JSON_PRETTY_PRINT));
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_map(
            fn (Package $package): string => $package->fullPackageString(),
            $this->aliases,
        );
    }
}
