# Changelog

All notable changes to `asignua/filament-image-meta` are documented here.

## v1.0.2 - 2026-10-08

- Dependencies: esbuild 0.28 (dev); the built asset is unchanged.
- A field outside a Repeater or Builder item (however many Sections or wrappers it sits in) compares a save with the record's stored column, not with the state the browser holds, so the text length limit cannot be lifted nor foreign texts injected by writing the reserved `_stored` key; the `requireAlt` rule also recognises stored images with other extensions (`heic`, `tif`, …) and, with no extension, asks the upload's mime type.
- `requireAlt` now checks images that are already stored too, not only the files uploaded in the current request: it is a rule of the details panel (always present in the form), so an existing record with an undescribed image, or one whose alt text was cleared in the modal, no longer saves silently, on both storages. The error is shown on the panel, which now sits in the field wrapper. With `requireAlt` off, no empty rule reaches the validator any more (no PHP deprecation per upload).
- An upload inside a Repeater or Builder item (JSON, no relationship) keeps the details it does not collect and the stored long texts on save: the stored snapshot lives in the panel's own state (reserved key `_stored`) instead of being read from the parent record; it is HMAC-signed (app key, bound to the field name (the state path is not stable: a Repeater re-keys its items after hydration)), and a missing or forged snapshot counts as "nothing stored".
- A required language counts as described only by its own alt text (or a language-less one); `fallback_locale` is for rendering only and no longer makes the badge say "Alt text set" or lets `requireAlt` pass. New `ImageMeta::ownAlt()` and `hasOwnAlt()`.
- Applying the modal of a field without `locales` no longer collapses alt text, caption or title written per language into one string: a text that was not edited keeps the stored map.
- The panel reads the upload's files once per state instead of on every render, rule run and modal opening (no repeated size, mime type and URL requests per file on remote disks); the `requireAlt` rule asks the disk for nothing.
- The alt text under a file name is shown in a language the field manages (the interface language, else the first required one), so it matches the badge.

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
