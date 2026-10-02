# Changelog

All notable changes to `asignua/filament-image-meta` are documented here.

## v1.0.0 - unreleased

- `ImageMetaUpload::make($upload, ...)` adds per-file details to a core `FileUpload` or `SpatieMediaLibraryFileUpload`: alt text (optionally per locale), a "decorative image" flag, caption, title and a focal point.
- An "Edit details" action per uploaded image (modal) with a status badge per row: alt set, decorative, alt missing, focal point set.
- Focal-point picker: click, drag (pointer events: mouse, touch, pen) or keyboard (arrow keys, Shift for bigger steps, Home/Escape to reset), as a small Alpine component loaded on demand.
- Storage-agnostic: a plain `FileUpload` writes a sibling JSON attribute (`photo_meta`, keyed by file path); `SpatieMediaLibraryFileUpload` writes the `custom_properties` of each media item, with configurable property names (defaults `alt`, `alt_decorative`, `caption`, `title`, `focal_point`).
- `requireAlt`: validation error unless every image has alt text (in the required locales) or is marked decorative.
- `ImageMeta` value object (`for()`, `allFor()`, `forMedia()`, `alt()`, `altAttribute()`, `isDecorative()`, `isMissingAlt()`, `caption()`, `title()`, `objectPosition()`), with a bounded focal point (0..100).
- `<x-image-meta::img>` and `<x-image-meta::figure>`: decorative and undescribed images get `alt=""`, never the file name; `object-position` from the focal point; optional `data-alt-missing` marker.
- Translations: English, Ukrainian, German, Spanish, French, Italian, Dutch, Polish, Brazilian Portuguese and Turkish.
- Laravel Boost guidelines.
