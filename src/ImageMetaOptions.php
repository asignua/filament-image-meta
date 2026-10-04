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
     * Cut a payload down to what this field collects and normalise it.
     *
     * `$limit` cuts the texts to the configured lengths. It is meant for writes that come from
     * the browser; values read from storage are passed with `$limit: false`, so that lowering
     * `alt_max_length` (or reading texts written by another tool) does not silently shorten
     * them. See {@see limitTexts()} for the write path that keeps the stored texts.
     *
     * @param array<string, mixed> $data
     */
    public function normalise(array $data, bool $limit = true): ImageMeta
    {
        $allowed = [
            'alt' => $this->alt ? ($data['alt'] ?? null) : null,
            'decorative' => $this->decorative ? ($data['decorative'] ?? false) : false,
            'caption' => $this->caption ? ($data['caption'] ?? null) : null,
            'title' => $this->title ? ($data['title'] ?? null) : null,
            'focal' => $this->focalPoint ? ($data['focal'] ?? null) : null,
        ];

        $meta = ImageMeta::fromArray($allowed);

        $meta = new ImageMeta(
            alt: $this->onlyLocales($meta->alt),
            decorative: $meta->decorative,
            caption: $this->onlyLocales($meta->caption),
            title: $this->onlyLocales($meta->title),
            focal: $meta->focal,
        );

        return $limit ? $this->limitTexts($meta) : $meta;
    }

    /**
     * Cut the texts to the configured lengths. The panel's state is a public Livewire property:
     * the modal's `maxLength` is only a hint to an honest browser, so the limits are enforced
     * again on every write. A text equal to the one in `$stored` (the same language) is kept
     * as it is: it was not written by the browser, and cutting it would lose it silently.
     */
    public function limitTexts(ImageMeta $meta, ?ImageMeta $stored = null): ImageMeta
    {
        return new ImageMeta(
            alt: $this->limit($meta->alt, self::altMaxLength(), $stored->alt ?? []),
            decorative: $meta->decorative,
            caption: $this->limit($meta->caption, self::TEXT_MAX_LENGTH, $stored->caption ?? []),
            title: $this->limit($meta->title, self::TEXT_MAX_LENGTH, $stored->title ?? []),
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
     * Put back into `$meta` what this field does not manage, taken from `$stored` (the details as
     * they are stored now): every detail the field does not collect, and the texts in languages
     * outside `locales` (an earlier language list, another site or tool). The details this field
     * collects, in its own languages, are taken from `$meta` as they are. A decorative image keeps
     * no alt text in any language.
     */
    public function keepUnmanaged(ImageMeta $meta, ?ImageMeta $stored): ImageMeta
    {
        if ($stored === null) {
            return $meta;
        }

        $decorative = $this->alt && $this->decorative ? $meta->decorative : $stored->decorative;

        return new ImageMeta(
            alt: $this->alt ? ($meta->decorative ? [] : $this->withOtherLocales($meta->alt, $stored->alt)) : $stored->alt,
            decorative: $decorative,
            caption: $this->caption ? $this->withOtherLocales($meta->caption, $stored->caption) : $stored->caption,
            title: $this->title ? $this->withOtherLocales($meta->title, $stored->title) : $stored->title,
            focal: $this->focalPoint ? $meta->focal : $stored->focal,
        );
    }

    /**
     * @param array<string, string> $texts
     * @param array<string, string> $stored
     *
     * @return array<string, string>
     */
    private function withOtherLocales(array $texts, array $stored): array
    {
        if (!$this->isLocalised()) {
            return $texts;
        }

        // The language-less text is not "another language": the modal offers it to the first
        // language, and a submitted modal drops it.
        return $texts + array_diff_key($stored, array_flip([...$this->locales, ImageMeta::ANY]));
    }

    /**
     * @param array<string, string> $texts
     * @param array<string, string> $keep  texts that are kept even when they are longer
     *
     * @return array<string, string>
     */
    private function limit(array $texts, int $max, array $keep = []): array
    {
        foreach ($texts as $locale => $text) {
            if (($keep[$locale] ?? null) !== $text) {
                $texts[$locale] = rtrim(mb_substr($text, 0, $max));
            }
        }

        return $texts;
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
