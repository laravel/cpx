<?php

declare(strict_types=1);

namespace Cpx\Support;

use Symfony\Component\Console\Output\OutputInterface;

readonly class JsonEnvelope
{
    /**
     * @param  list<string>  $errors
     * @param  array<string, mixed>  $summary
     */
    private function __construct(
        public bool $success,
        public array $errors,
        public array $summary,
    ) {}

    /** @param array<string, mixed> $summary */
    public static function success(array $summary = []): self
    {
        return new self(true, [], $summary);
    }

    /**
     * @param  string|list<string>  $errors
     * @param  array<string, mixed>  $summary
     */
    public static function failure(string|array $errors, array $summary = []): self
    {
        return new self(false, is_array($errors) ? $errors : [$errors], $summary);
    }

    public function write(OutputInterface $output): void
    {
        $output->writeln(
            json_encode([
                'success' => $this->success,
                'errors' => $this->errors,
                'summary' => $this->summary,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            OutputInterface::OUTPUT_RAW,
        );
    }
}
