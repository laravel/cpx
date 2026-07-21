<?php

declare(strict_types=1);

namespace Cpx\Exceptions;

use Cpx\Packages\Package;
use Exception;
use Laravel\Prompts\Elements\Link;
use Throwable;

use function Laravel\Prompts\callout;

class PackageNotFoundException extends Exception
{
    public function __construct(public readonly Package $package, ?Throwable $previous = null)
    {
        parent::__construct("The package \"{$package->fullPackageString()}\" could not be found.", previous: $previous);
    }

    public static function fromPackageString(string $package, ?Throwable $previous = null): self
    {
        return new self(Package::parse($package), $previous);
    }

    public function render(): void
    {
        callout(
            label: 'Package not found',
            content: [
                "Composer was unable to install `{$this->package->fullPackageString()}`.",
                $this->hint(),
                new Link("https://packagist.org/packages/{$this->package->vendor}/{$this->package->name}"),
            ],
            type: 'error',
        );
    }

    private function hint(): string
    {
        return $this->package->version === null
            ? 'Make sure the package name is spelled correctly and the package is published on Packagist:'
            : "Make sure the package name is spelled correctly and a version matching `{$this->package->version}` is published on Packagist:";
    }
}
