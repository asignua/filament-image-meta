<?php

declare(strict_types=1);

namespace Asignua\FilamentImageMeta;

use Closure;

/**
 * What one upload field collects. Built by `ImageMetaUpload::make(FileUpload::make('photo'), ...)`.
 */
final readonly class ImageMetaOptions
{
    /** The longest caption or title, in characters. */
    public const int TEXT_MAX_LENGTH = 255;

    /**
     * @param array<int, string> $locales         empty = one language-less value
     * @param array<int, string> $requiredLocales locales that must have alt text when `requireAlt` is on (default: the first)
     */
    public function __construct(
        public bool $alt = true,
        public bool $decorative = true,
        public bool $caption = false,
        public bool $title = false,
        public bool $focalPoint = false,
        public array $locales = [],
        public bool|Closure $requireAlt = false,
        public ?string $statePath = null,
        public array $requiredLocales = [],
    ) {}

    public function isLocalised(): bool
    {
        return $this->locales !== [];
    }

    /**
     * @return array<int, string>
     */
    public function altLocalesRequired(): array
    {
        if ($this->requiredLocales !== []) {
            return $this->requiredLocales;
        }

        return $this->locales === [] ? [ImageMeta::ANY] : [$this->locales[0]];
    }

    /**
     * Cut a submitted modal payload down to what this field collects and normalise it.
     *
     * @param array<string, mixed> $data
     */
    public function normalise(array $data): ImageMeta
    {
        $allowed = [
            'alt' => $this->alt ? ($data['alt'] ?? null) : null,
            'decorative' => $this->decorative ? ($data['decorative'] ?? false) : false,
            'caption' => $this->caption ? ($data['caption'] ?? null) : null,
            'title' => $this->title ? ($data['title'] ?? null) : null,
            'focal' => $this->focalPoint ? ($data['focal'] ?? null) : null,
        ];

        $meta = ImageMeta::fromArray($allowed);

        // The panel's state is a public Livewire property: the modal's `maxLength` is only a hint
        // to an honest browser, so the limits are enforced again here, on every write.
        $altMax = self::altMaxLength();

        return new ImageMeta(
            alt: $this->limit($this->onlyLocales($meta->alt), $altMax),
            decorative: $meta->decorative,
            caption: $this->limit($this->onlyLocales($meta->caption), self::TEXT_MAX_LENGTH),
            title: $this->limit($this->onlyLocales($meta->title), self::TEXT_MAX_LENGTH),
            focal: $meta->focal,
        );
    }

    /**
     * The longest alt text, in characters (config `image-meta.alt_max_length`).
     */
    public static function altMaxLength(): int
    {
        return max(1, (int) config('image-meta.alt_max_length', 250));
    }

    /**
     * @param array<string, string> $texts
     *
     * @return array<string, string>
     */
    private function limit(array $texts, int $max): array
    {
        return array_map(static fn (string $text): string => rtrim(mb_substr($text, 0, $max)), $texts);
    }

    /**
     * @param array<string, string> $texts
     *
     * @return array<string, string>
     */
    private function onlyLocales(array $texts): array
    {
        if (!$this->isLocalised()) {
            return $texts;
        }

        // A language-less text (from before `locales:` was configured) is kept so the modal can
        // offer it to the first language; a submitted modal never contains one.
        return array_intersect_key($texts, array_flip([...$this->locales, ImageMeta::ANY]));
    }
}
