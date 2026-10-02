<?php

declare(strict_types=1);

namespace Asignua\FilamentImageMeta;

use Closure;

/**
 * What one upload field collects. Built by `FileUpload::imageMeta(...)`.
 */
final readonly class ImageMetaOptions
{
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

        if ($this->isLocalised()) {
            $meta = new ImageMeta(
                alt: $this->onlyLocales($meta->alt),
                decorative: $meta->decorative,
                caption: $this->onlyLocales($meta->caption),
                title: $this->onlyLocales($meta->title),
                focal: $meta->focal,
            );
        }

        return $meta;
    }

    /**
     * @param array<string, string> $texts
     *
     * @return array<string, string>
     */
    private function onlyLocales(array $texts): array
    {
        // A language-less text (from before `locales:` was configured) is kept so the modal can
        // offer it to the first language; a submitted modal never contains one.
        return array_intersect_key($texts, array_flip([...$this->locales, ImageMeta::ANY]));
    }
}
