<?php

declare(strict_types=1);

namespace Cpx\Input;

use InvalidArgumentException;

class PackageInvocation
{
    /**
     * @param  list<string>  $forwardedTokens
     */
    public function __construct(
        public string $target,
        private array $forwardedTokens = [],
    ) {
        $this->target = trim($this->target);

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
}
