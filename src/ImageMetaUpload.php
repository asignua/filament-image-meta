<?php

declare(strict_types=1);

namespace Asignua\FilamentImageMeta;

use Asignua\FilamentImageMeta\Forms\ImageMetaPanel;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Group;

/**
 * Adds per-file details (alt text, "decorative", caption, title, focal point) to a core file upload.
 *
 *     ImageMetaUpload::make(
 *         FileUpload::make('photo')->image()->disk('public'),
 *         locales: ['en', 'uk'],
 *         focalPoint: true,
 *         requireAlt: true,
 *     )
 *
 * Works with `FileUpload` (the details go to a sibling `{name}_meta` JSON column, keyed by file
 * path) and `SpatieMediaLibraryFileUpload` (the details go to the `custom_properties` of each
 * media item). The upload itself is not modified apart from one validation rule and, for the media
 * library, one extra step after its own relationship save.
 *
 * It returns a plain, state-less `Group` holding the upload and the details panel as SIBLINGS.
 * That is deliberate: a panel nested inside the upload (`belowContent()`) gets its state path
 * under the upload's own, so it would write into the list of files.
 */
final class ImageMetaUpload
{
    /**
     * @param array<int, string> $locales         empty = one language-less value
     * @param array<int, string> $requiredLocales locales that need alt text when `requireAlt` is on (default: the first)
     * @param string|null        $statePath       name of the sibling attribute for a plain upload (default `{name}_meta`)
     */
    public static function make(
        BaseFileUpload $upload,
        bool $alt = true,
        bool $decorative = true,
        bool $caption = false,
        bool $title = false,
        bool $focalPoint = false,
        array $locales = [],
        bool|Closure $requireAlt = false,
        ?string $statePath = null,
        array $requiredLocales = [],
    ): Group {
        $options = new ImageMetaOptions(
            alt: $alt,
            decorative: $decorative,
            caption: $caption,
            title: $title,
            focalPoint: $focalPoint,
            locales: array_values($locales),
            requireAlt: $requireAlt,
            statePath: $statePath,
            requiredLocales: array_values($requiredLocales),
        );

        $storesInMedia = $upload instanceof SpatieMediaLibraryFileUpload;

        $panel = ImageMetaPanel::make($statePath ?? ($upload->getName().(string) config('image-meta.meta_suffix', '_meta')))
            ->configureFor($options, $storesInMedia);

        // Keep one slot per file: see ImageMetaPanel::syncSlots(). Appended to the upload's own
        // callbacks; `saveUploadedFiles()` and removing a file both call them.
        $upload->afterStateUpdated(static function (BaseFileUpload $component): void {
            ImageMetaPanel::siblingOf($component)?->syncSlots();
        });

        if ($storesInMedia) {
            $original = (fn (): ?Closure => $this->saveRelationshipsUsing)->call($upload);

            $upload->saveRelationshipsUsing(static function (SpatieMediaLibraryFileUpload $component) use ($original): void {
                if ($original instanceof Closure) {
                    $component->evaluate($original);
                }

                ImageMetaPanel::siblingOf($component)?->persistToMedia();
            });
        }

        if ($alt) {
            $upload->rule(static function (BaseFileUpload $component) use ($options): ?Closure {
                $panel = ImageMetaPanel::siblingOf($component);

                if (!$panel instanceof ImageMetaPanel || !(bool) $component->evaluate($options->requireAlt)) {
                    return null;
                }

                return static function (string $attribute, mixed $value, Closure $fail) use ($panel): void {
                    foreach ($panel->getRows() as $row) {
                        if ($panel->statusOf($row['meta']) === 'missing') {
                            $fail(__('image-meta::image-meta.alt_required', ['file' => $row['name']]));
                        }
                    }
                };
            });
        }

        return Group::make([$upload, $panel])->columnSpanFull();
    }
}
