<?php

declare(strict_types=1);

namespace Cpx\Input;

use InvalidArgumentException;

class PackageInvocation
{
    /** @var array<string, string|null|list<string|null>>|null */
    private ?array $parsedOptions = null;

    /**
     * @param  list<string>  $forwardedTokens
     */
    public function __construct(
        public string $target,
        private array $forwardedTokens = [],
    ) {
        if ($this->target === '') {
            throw new InvalidArgumentException('A package invocation target must be provided.');
        }
    }

    /**
     * @param  list<string>  $tokens
     */
    public static function fromRawTokens(array $tokens): self
    {
        $target = array_shift($tokens);

        return new self(is_string($target) ? $target : '', $tokens);
    }

    /** @return list<string> */
    public function forwardedTokens(): array
    {
        return $this->forwardedTokens;
    }

    public function firstForwardedToken(): ?string
    {
        return $this->forwardedTokens[0] ?? null;
    }

    public function withoutFirstForwardedToken(): self
    {
        return new self($this->target, array_slice($this->forwardedTokens, 1));
    }

    public function hasOption(string $option): bool
    {
        return array_key_exists($option, $this->options());
    }

    /**
     * Returns a single value for the option. When an option is repeated
     * (e.g. --filter=one --filter=two) the first non-null value wins.
     */
    public function option(string $option): ?string
    {
        $value = $this->options()[$option] ?? null;

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $optionValue) {
            if ($optionValue !== null) {
                return $optionValue;
            }
        }

        return null;
    }

    /** @return array<string, string|null|list<string|null>> */
    private function options(): array
    {
        if ($this->parsedOptions !== null) {
            return $this->parsedOptions;
        }

        $options = [];
        $tokens = $this->forwardedTokens;

        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];

            if ($token === '--') {
                break;
            }

            if (preg_match('/^--(?<name>[A-Z\d\-_]+)(?:=(?<value>.*))?$/i', $token, $matches) !== 1) {
                continue;
            }

            $value = array_key_exists('value', $matches) ? $matches['value'] : null;

            if ($value === null && isset($tokens[$index + 1]) && ! str_starts_with($tokens[$index + 1], '-')) {
                $value = $tokens[$index + 1];
                $index++;
            }

            $this->addOption($options, $matches['name'], $value);
        }

        return $this->parsedOptions = $options;
    }

    /**
     * @param  array<string, string|null|list<string|null>>  $options
     */
    private function addOption(array &$options, string $name, ?string $value): void
    {
        if (! array_key_exists($name, $options)) {
            $options[$name] = $value;

            return;
        }

        if (is_array($options[$name])) {
            $options[$name][] = $value;

            return;
        }

        $options[$name] = [$options[$name], $value];
    }
}
