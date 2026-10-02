<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Media-library custom properties
    |--------------------------------------------------------------------------
    |
    | Where `SpatieMediaLibraryFileUpload` keeps each detail, as `custom_properties` of the
    | media item. The defaults match the layout several existing sites already use
    | (`alt`, `alt_decorative`, `caption`), so adopting the plugin does not move any data.
    | `alt`, `caption` and `title` hold a string, or a `{locale: string}` map when the field
    | was given `locales:`; the reader accepts both.
    |
    */
    'media_properties' => [
        'alt' => 'alt',
        'decorative' => 'alt_decorative',
        'caption' => 'caption',
        'title' => 'title',
        'focal_point' => 'focal_point',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sibling attribute for a plain FileUpload
    |--------------------------------------------------------------------------
    |
    | `ImageMetaUpload::make(FileUpload::make('photo'), ...)` writes the details to
    | `photo_meta` (a JSON column cast to `array`, keyed by the stored file path). Change the
    | suffix here, or pass `statePath: 'other_column'` to `ImageMetaUpload::make()` for one field.
    |
    */
    'meta_suffix' => '_meta',

    /*
    |--------------------------------------------------------------------------
    | Locale fallback for alt text
    |--------------------------------------------------------------------------
    |
    | null = never. An empty alt in the requested language stays empty rather than showing
    | another language's sentence. Set a locale (e.g. 'en') to fall back to it.
    |
    */
    'fallback_locale' => null,

    /*
    |--------------------------------------------------------------------------
    | Mark images without alt text
    |--------------------------------------------------------------------------
    |
    | When true, <x-image-meta::img> adds `data-alt-missing` to an image that is neither
    | described nor marked decorative, so an audit (or a CSS outline in staging) finds it.
    |
    */
    'mark_missing_alt' => false,

    /*
    |--------------------------------------------------------------------------
    | Maximum alt text length
    |--------------------------------------------------------------------------
    */
    'alt_max_length' => 250,

];
