<?php

declare(strict_types=1);

namespace Cpx\Gists;

readonly class GistFile
{
    public function __construct(
        public string $filename,
        public ?string $language,
        public string $content,
        public bool $truncated = false,
        public ?string $rawUrl = null,
    ) {
        //
    }

    public function isPhp(): bool
    {
        return $this->language === 'PHP'
            || str_ends_with(strtolower($this->filename), '.php');
    }

    public function hasPhpTag(): bool
    {
        return str_starts_with($this->content, '<?php');
    }

    /** The anchor slug GitHub generates for this file, e.g. `file-my-script-php`. */
    public function fragment(): string
    {
        return 'file-'.self::slug($this->filename);
    }

    public static function slug(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value));
    }
}
