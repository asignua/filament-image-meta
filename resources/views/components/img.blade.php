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

    // Built through the attribute bag, so an `alt`/`title`/`style` passed by the caller is merged
    // instead of being written a second time (a browser keeps only the FIRST of two attributes):
    // the caller's `alt` and `title` win, a caller's `style` is kept and the focal point appended.
    $imgAttributes = $attributes->merge(['alt' => $alt]);

    if (filled($title) && ! $details->isDecorative()) {
        $imgAttributes = $imgAttributes->merge(['title' => $title]);
    }

    if ($position) {
        $imgAttributes = $imgAttributes->style(['object-position: ' . $position]);
    }
@endphp

<img
    src="{{ $src }}"
    {{ $imgAttributes }}
    @if (config('image-meta.mark_missing_alt') && $details->isMissingAlt($locale)) data-alt-missing @endif
/>
