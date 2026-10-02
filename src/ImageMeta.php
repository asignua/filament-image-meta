<?php

declare(strict_types=1);

namespace Asignua\FilamentImageMeta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The details of ONE image, whatever the storage: alt text, the "decorative" flag,
 * caption, title and a focal point.
 *
 *     $meta = ImageMeta::for($post, 'photo');   // FileUpload column or media collection
 *     $meta->altAttribute();                    // the exact value of the alt="" attribute
 *     $meta->objectPosition();                  // "35% 20%" or null
 *
 * Text fields are maps `locale => string`; a value stored as a plain string applies to every
 * language (key `*`). There is deliberately NO fallback to the file name: a missing alt is
 * rendered as `alt=""` and reported by {@see isMissingAlt()}, never as the name of the file.
 */
final readonly class ImageMeta
{
    /** The key of a language-less text. */
    public const string ANY = '*';

    /**
     * @param array<string, string>          $alt
     * @param array<string, string>          $caption
     * @param array<string, string>          $title
     * @param array{x: float, y: float}|null $focal   percentages, 0..100
     */
    public function __construct(
        public array $alt = [],
        public bool $decorative = false,
        public array $caption = [],
        public array $title = [],
        public ?array $focal = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    /**
     * Normalise any stored shape (null, JSON string, partial array, a plain-string alt).
     */
    public static function fromArray(mixed $raw): self
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            return new self;
        }

        $decorative = filter_var($raw['decorative'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return new self(
            alt: $decorative ? [] : self::texts($raw['alt'] ?? null),
            decorative: $decorative,
            caption: self::texts($raw['caption'] ?? null),
            title: self::texts($raw['title'] ?? null),
            focal: self::focal($raw['focal'] ?? null),
        );
    }

    /**
     * The details of the image stored in `$attribute` of the model.
     *
     * - A column holding a path (or a list of paths) uploaded with `FileUpload::imageMeta()`:
     *   read from the sibling `{$attribute}_meta` column; pass `$path` to pick one of many.
     * - A media-library collection name (when the model has no such column): the first media
     *   item of the collection, read from its custom properties.
     */
    public static function for(Model $model, string $attribute, ?string $path = null, ?string $metaAttribute = null): self
    {
        if ($model instanceof HasMedia && !array_key_exists($attribute, $model->getAttributes())) {
            $media = $model->getFirstMedia($attribute);

            return $media instanceof Media ? self::forMedia($media) : new self;
        }

        $all = self::allFor($model, $attribute, $metaAttribute);

        if ($path === null) {
            $value = $model->getAttribute($attribute);
            $path = is_array($value) ? Arr::first($value) : $value;
        }

        return is_string($path) ? ($all[$path] ?? new self) : new self;
    }

    /**
     * Every stored image of a FileUpload column, keyed by file path.
     *
     * @return array<string, self>
     */
    public static function allFor(Model $model, string $attribute, ?string $metaAttribute = null): array
    {
        $metaAttribute ??= $attribute.(string) config('image-meta.meta_suffix', '_meta');

        $stored = $model->getAttribute($metaAttribute);

        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        if (!is_array($stored)) {
            return [];
        }

        $all = [];

        foreach ($stored as $path => $raw) {
            $all[(string) $path] = self::fromArray($raw);
        }

        return $all;
    }

    /**
     * The details of a media-library item, from its custom properties
     * (names: config `image-meta.media_properties`).
     */
    public static function forMedia(Media $media): self
    {
        $properties = self::mediaProperties();

        return self::fromArray([
            'alt' => $media->getCustomProperty($properties['alt']),
            'decorative' => $media->getCustomProperty($properties['decorative'], false),
            'caption' => $media->getCustomProperty($properties['caption']),
            'title' => $media->getCustomProperty($properties['title']),
            'focal' => $media->getCustomProperty($properties['focal_point']),
        ]);
    }

    /**
     * @return array{alt: string, decorative: string, caption: string, title: string, focal_point: string}
     */
    public static function mediaProperties(): array
    {
        /** @var array<string, string> $configured */
        $configured = (array) config('image-meta.media_properties', []);

        return [
            'alt' => $configured['alt'] ?? 'alt',
            'decorative' => $configured['decorative'] ?? 'alt_decorative',
            'caption' => $configured['caption'] ?? 'caption',
            'title' => $configured['title'] ?? 'title',
            'focal_point' => $configured['focal_point'] ?? 'focal_point',
        ];
    }

    /**
     * The alt text for a language, or null when there is none (or the image is decorative).
     */
    public function alt(?string $locale = null): ?string
    {
        return $this->decorative ? null : self::pick($this->alt, $locale);
    }

    /**
     * The exact value for the `alt` attribute: the text, or `''` for a decorative image AND for
     * an image nobody described. Never the file name.
     */
    public function altAttribute(?string $locale = null): string
    {
        return $this->alt($locale) ?? '';
    }

    public function isDecorative(): bool
    {
        return $this->decorative;
    }

    public function hasAlt(?string $locale = null): bool
    {
        return $this->alt($locale) !== null;
    }

    /**
     * True for an image that is neither decorative nor described: the thing an audit looks for.
     */
    public function isMissingAlt(?string $locale = null): bool
    {
        return !$this->decorative && !$this->hasAlt($locale);
    }

    public function caption(?string $locale = null): ?string
    {
        return self::pick($this->caption, $locale);
    }

    public function title(?string $locale = null): ?string
    {
        return self::pick($this->title, $locale);
    }

    public function hasFocalPoint(): bool
    {
        return $this->focal !== null;
    }

    /**
     * The CSS `object-position` value ("35% 20%"), or null without a focal point.
     * Pair it with `object-fit: cover` (Tailwind `object-cover`), otherwise it does nothing.
     */
    public function objectPosition(): ?string
    {
        if ($this->focal === null) {
            return null;
        }

        return self::number($this->focal['x']).'% '.self::number($this->focal['y']).'%';
    }

    public function isEmpty(): bool
    {
        return $this->toArray() === [];
    }

    /**
     * The storable shape; empty parts are left out. A text whose only language is
     * {@see ANY} is stored as a plain string.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = array_filter([
            'alt' => self::packTexts($this->alt),
            'decorative' => $this->decorative ?: null,
            'caption' => self::packTexts($this->caption),
            'title' => self::packTexts($this->title),
            'focal' => $this->focal,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        return $data;
    }

    /**
     * @param array<string, string> $texts
     */
    private static function pick(array $texts, ?string $locale): ?string
    {
        $locale ??= app()->getLocale();

        $fallback = config('image-meta.fallback_locale');

        $text = $texts[$locale] ?? $texts[self::ANY] ?? (is_string($fallback) ? ($texts[$fallback] ?? null) : null);

        return ($text === null || $text === '') ? null : $text;
    }

    /**
     * @return array<string, string>
     */
    private static function texts(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = [self::ANY => $raw];
        }

        if (!is_array($raw)) {
            return [];
        }

        $texts = [];

        foreach ($raw as $locale => $text) {
            if (!is_string($text) && !is_numeric($text)) {
                continue;
            }

            $text = trim((string) $text);

            if ($text !== '') {
                $texts[(string) $locale] = $text;
            }
        }

        return $texts;
    }

    /**
     * @param array<string, string> $texts
     *
     * @return array<string, string>|string|null
     */
    private static function packTexts(array $texts): string|array|null
    {
        if ($texts === []) {
            return null;
        }

        if (array_keys($texts) === [self::ANY]) {
            return $texts[self::ANY];
        }

        return $texts;
    }

    /**
     * A focal point is two percentages. Anything out of range is pulled back inside the image;
     * anything that is not a pair of numbers is no focal point at all.
     *
     * @return array{x: float, y: float}|null
     */
    public static function focal(mixed $raw): ?array
    {
        if (!is_array($raw) || !isset($raw['x'], $raw['y']) || !is_numeric($raw['x']) || !is_numeric($raw['y'])) {
            return null;
        }

        $x = (float) $raw['x'];
        $y = (float) $raw['y'];

        if (!is_finite($x) || !is_finite($y)) {
            return null;
        }

        return [
            'x' => round(max(0.0, min(100.0, $x)), 2),
            'y' => round(max(0.0, min(100.0, $y)), 2),
        ];
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }
}
