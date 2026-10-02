@props([
    'src',
    'meta' => null,
    'locale' => null,
    'imgClass' => null,
])

@php
    use Asignua\FilamentImageMeta\ImageMeta;

    $details = $meta instanceof ImageMeta ? $meta : ImageMeta::fromArray($meta);
    $caption = $details->caption($locale);
@endphp

<figure {{ $attributes }}>
    <x-image-meta::img :src="$src" :meta="$details" :locale="$locale" :class="$imgClass" />

    @if (filled($caption))
        <figcaption>{{ $caption }}</figcaption>
    @endif
</figure>
