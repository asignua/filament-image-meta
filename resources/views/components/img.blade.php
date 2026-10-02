@props([
    'src',
    'meta' => null,
    'locale' => null,
])

@php
    use Asignua\FilamentImageMeta\ImageMeta;

    /** @var ImageMeta $details */
    $details = $meta instanceof ImageMeta ? $meta : ImageMeta::fromArray($meta);

    // `alt=""` for a decorative image AND for one nobody described: an <img> WITHOUT alt makes a
    // screen reader read the file name or the URL, which is worse than silence. A missing alt is
    // never replaced with the name of the file.
    $alt = $details->altAttribute($locale);
    $position = $details->objectPosition();
    $title = $details->title($locale);
@endphp

<img
    src="{{ $src }}"
    alt="{{ $alt }}"
    @if (filled($title) && ! $details->isDecorative()) title="{{ $title }}" @endif
    @if ($position) style="object-position: {{ $position }}" @endif
    @if (config('image-meta.mark_missing_alt') && $details->isMissingAlt($locale)) data-alt-missing @endif
    {{ $attributes }}
/>
