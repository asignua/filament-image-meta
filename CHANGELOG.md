# Changelog

All notable changes to `asignua/filament-image-meta` are documented here.

## v1.0.1 - 2026-10-05

- A save keeps the alt, caption and title texts in languages outside the field's `locales` (an earlier language list, texts written by another tool), on both storages; before, every save rewrote each file's texts with the configured languages only.
- A plain `FileUpload` keeps the details it does not collect (`caption`, `title`, the focal point) in its `{name}_meta` entry, as the media-library path already did; a new file stored under the path of a removed one still inherits nothing.
- Clearing every detail the field collects in the modal (an empty alt, say) no longer erases what it does not manage on a plain `FileUpload`: the stored caption, title, focal point and texts in other languages are kept.

## v1.0.0 - 2026-10-03

- `ImageMetaUpload::make($upload, ...)` adds per-file details to a core `FileUpload` or `SpatieMediaLibraryFileUpload`: alt text (optionally per locale), a "decorative image" flag, caption, title and a focal point.
- An "Edit details" action per uploaded image (modal) with a status badge per row: alt set, decorative, alt missing, focal point set.
- Focal-point picker: click, drag (pointer events: mouse, touch, pen) or keyboard (arrow keys, Shift for bigger steps, Home or the Reset button to reset; Escape is left to the modal), as a small Alpine component loaded on demand.
- Storage-agnostic: a plain `FileUpload` writes a sibling JSON attribute (`photo_meta`, keyed by file path); `SpatieMediaLibraryFileUpload` writes the `custom_properties` of each media item, with configurable property names (defaults `alt`, `alt_decorative`, `caption`, `title`, `focal_point`).
- `requireAlt`: validation error unless every image has alt text (in the required locales) or is marked decorative.
- `ImageMeta` value object (`for()`, `allFor()`, `forMedia()`, `alt()`, `altAttribute()`, `isDecorative()`, `isMissingAlt()`, `caption()`, `title()`, `objectPosition()`), with a bounded focal point (0..100).
- `<x-image-meta::img>` and `<x-image-meta::figure>`: decorative and undescribed images get `alt=""`, never the file name; `object-position` from the focal point; optional `data-alt-missing` marker.
- Translations: English, Ukrainian, German, Spanish, French, Italian, Dutch, Polish, Brazilian Portuguese and Turkish.
- Laravel Boost guidelines.
- The details panel follows its upload: a disabled upload gives a read-only panel whose details are not saved, a hidden upload hides the panel. Texts written straight into the panel's state are cut to the configured lengths (`alt_max_length`, 255 for caption and title). Stored texts are not: a text longer than the limit that nobody edited is saved back unchanged, so lowering `alt_max_length` never shortens existing alt texts silently.
- One set of details per file: removing a file drops its details, and a file uploaded again under the same path (`preserveFilenames()`, a deterministic file name) or the same media uuid does not inherit the removed file's details.
- A media-library field writes and removes only the details it collects: with `title: false` (the default), no focal point or `alt: false`, the matching custom properties of a media item are left as they are instead of being erased on save.
