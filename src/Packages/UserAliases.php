<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Cpx\Exceptions\MalformedAliasesException;
use Cpx\Support\Filesystem;
use Cpx\Support\Interactivity;
use InvalidArgumentException;

use function Laravel\Prompts\warning;

class UserAliases
{
    private const FILE = 'aliases.json';

    /**
     * @param  array<string, Package>  $aliases
     * @param  array<string, mixed>  $broken
     */
    protected function __construct(
        protected array $aliases = [],
        protected array $broken = [],
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
        $broken = [];

        foreach ($json as $name => $value) {
            try {
                $aliases[(string) $name] = self::parseEntry($file, $name, $value);
            } catch (MalformedAliasesException|InvalidArgumentException) {
                $broken[(string) $name] = $value;
            }
        }

        if ($broken !== [] && Interactivity::isInteractive()) {
            warning('Skipped aliases that could not be loaded: '.implode(', ', array_keys($broken)).'. Remove them with `cpx unalias <name>`.');
        }

        return new self($aliases, $broken);
    }

    /** @return array<string, Package> */
    public function all(): array
    {
        return $this->aliases;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->aliases) || array_key_exists($name, $this->broken);
    }

    public function find(string $name): ?Package
    {
        return $this->aliases[$name] ?? null;
    }

    public function put(string $name, Package $package): self
    {
        $this->aliases[$name] = $package;
        unset($this->broken[$name]);

        return $this;
    }

    public function remove(string $name): self
    {
        unset($this->aliases[$name], $this->broken[$name]);

        return $this;
    }

    public function save(): void
    {
        // Broken entries stay in the file untouched until removed with `unalias`.
        Filesystem::writeAtomic(cpx_path(self::FILE), (string) json_encode($this->toArray() + $this->broken, JSON_PRETTY_PRINT));
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

    /**
     * @throws MalformedAliasesException
     * @throws InvalidArgumentException
     */
    private static function parseEntry(string $file, int|string $name, mixed $value): Package
    {
        if (! is_string($name) || ! is_array($value)) {
            throw new MalformedAliasesException($file);
        }

        $package = $value['package'] ?? null;
        $bin = $value['bin'] ?? null;

        if (! is_string($package) || (! is_string($bin) && $bin !== null)) {
            throw new MalformedAliasesException($file);
        }

        return (LocalPackage::supports($package)
            ? LocalPackage::parse($package)
            : Package::parse($package))->withBin($bin);
    }
}
