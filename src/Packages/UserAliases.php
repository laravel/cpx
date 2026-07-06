<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Cpx\Exceptions\MalformedAliasesException;
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

    /**
     * @throws MalformedAliasesException
     */
    public static function open(): self
    {
        $file = cpx_path(self::FILE);

        if (! file_exists($file)) {
            return new self;
        }

        $json = json_decode((string) file_get_contents($file), true);

        if (! is_array($json)) {
            throw new MalformedAliasesException($file);
        }

        $aliases = [];

        foreach ($json as $name => $value) {
            if (! is_string($name) || ! is_array($value)) {
                throw new MalformedAliasesException($file);
            }

            $package = $value['package'] ?? null;
            $bin = $value['bin'] ?? null;

            if (! is_string($package) || (! is_string($bin) && $bin !== null)) {
                throw new MalformedAliasesException($file);
            }

            $aliases[$name] = Package::parse($package)->withBin($bin);
        }

        return new self($aliases);
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

    public function remove(string $name): self
    {
        unset($this->aliases[$name]);

        return $this;
    }

    public function save(): void
    {
        Filesystem::writeAtomic(cpx_path(self::FILE), (string) json_encode($this->toArray(), JSON_PRETTY_PRINT));
    }

    /** @return array<string, array{package: string, bin: string|null}> */
    public function toArray(): array
    {
        return array_map(
            fn (Package $package): array => [
                'package' => $package->fullPackageString(),
                'bin' => $package->bin,
            ],
            $this->aliases,
        );
    }
}
