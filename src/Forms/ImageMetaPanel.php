<?php

declare(strict_types=1);

namespace Asignua\FilamentImageMeta\Forms;

use Asignua\FilamentImageMeta\ImageMeta;
use Asignua\FilamentImageMeta\ImageMetaOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use LogicException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The list next to an upload: one row per file with its thumbnail, a status badge and the
 * "Edit details" button. Built by {@see \Asignua\FilamentImageMeta\ImageMetaUpload}; not meant to be used alone.
 *
 * STATE. The panel's own state is `slot => details`, never `path => details`: a path contains
 * dots, and a dot is a separator in a Livewire state path. A slot is `n<item key>` for a file
 * that is only an upload so far and `f<sha1 of the identifier>` for a stored file (the path
 * of a plain upload, the uuid of a media item). The identifier-keyed form lives only in the
 * database: it is built on hydrate and torn down on dehydrate.
 *
 * ONE SLOT PER FILE. Whenever the upload's state changes (a file added, removed, or stored by
 * `saveUploadedFiles()`), {@see syncSlots()} moves the `n<key>` slot of a stored file to its
 * identifier slot (overwriting it) and drops the slots of files that are gone. Without it, a
 * file removed and re-uploaded under the same path (`preserveFilenames()`, a deterministic
 * file name) would find the removed file's details waiting under the same identifier.
 */
class ImageMetaPanel extends Field
{
    public const string ACTION = 'editDetails';

    protected string $view = 'image-meta::panel';

    protected ImageMetaOptions $options;

    protected bool $storesInMedia = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hiddenLabel();

        $this->default([]);

        $this->registerActions([
            fn (self $component): Action => $component->getEditDetailsAction(),
        ]);

        $this->afterStateHydrated(static function (self $component): void {
            $component->rawState($component->hydrateSlots());
        });

        $this->dehydrateStateUsing(static fn (self $component): ?array => $component->exportForColumn());
    }

    public function configureFor(ImageMetaOptions $options, bool $storesInMedia): static
    {
        $this->options = $options;
        $this->storesInMedia = $storesInMedia;

        // A media item is written by the upload's own relationship save (see ImageMetaUpload);
        // the panel is not a column of the model then. A plain upload's details are saved only
        // when the upload itself is: a disabled or hidden upload must not have its details
        // rewritten through the panel.
        $this->dehydrated(static fn (self $component): bool => !$storesInMedia && $component->getUpload()->isDehydrated());

        // The panel mirrors the upload: whoever may not see or change the files may not see or
        // change their details either.
        $this->disabled(static fn (self $component): bool => $component->getUpload()->isDisabled());
        $this->hidden(static fn (self $component): bool => $component->getUpload()->isHidden());

        return $this;
    }

    public function getOptions(): ImageMetaOptions
    {
        return $this->options;
    }

    /**
     * The upload this panel belongs to: its sibling in the same container. Resolved from the
     * schema each time, because Filament clones a component when it is attached, so a reference
     * taken at build time would be a stale copy.
     */
    public function getUpload(): BaseFileUpload
    {
        foreach ($this->getContainer()->getComponents(withHidden: true) as $component) {
            if ($component instanceof BaseFileUpload) {
                return $component;
            }
        }

        throw new LogicException('The image-meta panel must sit next to a file upload (use ImageMetaUpload::make()).');
    }

    /**
     * The panel next to an upload, or null when the upload is not wrapped by ImageMetaUpload.
     */
    public static function siblingOf(BaseFileUpload $upload): ?self
    {
        foreach ($upload->getContainer()->getComponents(withHidden: true) as $component) {
            if ($component instanceof self) {
                return $component;
            }
        }

        return null;
    }

    // ---------------------------------------------------------------- slots & details

    public static function slotForKey(string|int $key): string
    {
        return 'n'.$key;
    }

    public static function slotForIdentifier(string $identifier): string
    {
        return 'f'.sha1($identifier);
    }

    /**
     * The slots that may hold the details of one file, most specific first: the identifier of a
     * stored file, then the item key. A file uploaded and saved in the same Livewire session
     * keeps its old `n<key>` slot (the save swaps the temporary file for its path under the
     * same key), and every later edit is written to the identifier slot, so that one must win.
     *
     * @return array<int, string>
     */
    public static function slotsFor(string|int $key, mixed $file): array
    {
        $candidates = [];

        if (is_string($file) && $file !== '') {
            $candidates[] = self::slotForIdentifier($file);
        }

        $candidates[] = self::slotForKey($key);

        return $candidates;
    }

    /**
     * The details of one file, looked up by its identifier first (a stored file), then by its
     * item key (an upload that has not been saved yet).
     *
     * @param array<string, mixed> $slots
     */
    public function lookup(array $slots, string|int $key, mixed $file): ImageMeta
    {
        $candidates = self::slotsFor($key, $file);

        foreach ($candidates as $slot) {
            if (isset($slots[$slot]) && is_array($slots[$slot])) {
                // Not cut to the length limits: this is also how stored details are read. The
                // write paths cut what the browser may have written, see exportForColumn().
                return $this->options->normalise($slots[$slot], limit: false);
            }
        }

        return ImageMeta::empty();
    }

    /**
     * @return array<string, mixed>
     */
    protected function slots(): array
    {
        $state = $this->getRawState();

        return is_array($state) ? $state : [];
    }

    public function metaForKey(string|int $key): ImageMeta
    {
        $files = $this->getUpload()->getRawState() ?? [];

        return $this->lookup($this->slots(), $key, $files[$key] ?? null);
    }

    /**
     * Stored form -> slots. Plain uploads read the column (`path => details`), media uploads the
     * custom properties of the record's media.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function hydrateSlots(): array
    {
        $slots = [];

        if ($this->storesInMedia) {
            $record = $this->getRecord();

            if ($record instanceof Model && $record instanceof HasMedia) {
                foreach ($this->mediaOf($record) as $media) {
                    $meta = ImageMeta::forMedia($media);

                    if (!$meta->isEmpty()) {
                        $slots[self::slotForIdentifier((string) $media->uuid)] = $meta->toArray();
                    }
                }
            }

            return $slots;
        }

        $stored = $this->getRawState();

        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        foreach (is_array($stored) ? $stored : [] as $path => $raw) {
            $meta = $this->options->normalise(ImageMeta::fromArray($raw)->toArray(), limit: false);

            if (!$meta->isEmpty()) {
                $slots[self::slotForIdentifier((string) $path)] = $meta->toArray();
            }
        }

        return $slots;
    }

    /**
     * Slots -> the column (`path => details`) for the files that are really kept.
     *
     * @return array<string, array<string, mixed>>|null
     */
    protected function exportForColumn(): ?array
    {
        // Idempotent: a file that is already stored passes through. Doing it here makes the
        // result independent of whether the upload dehydrates before or after the panel.
        $this->getUpload()->saveUploadedFiles();

        $slots = $this->syncSlots();
        $stored = $this->storedInColumn();
        $out = [];

        foreach ($this->getUpload()->getRawState() ?? [] as $key => $file) {
            if (!is_string($file) || $file === '') {
                continue;
            }

            $meta = $this->options->limitTexts($this->lookup($slots, $key, $file), $stored[$file] ?? null);

            if (!$meta->isEmpty()) {
                $out[$file] = $meta->toArray();
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * The details as they are stored in the record's column now, keyed by file path.
     *
     * @return array<string, ImageMeta>
     */
    protected function storedInColumn(): array
    {
        $record = $this->getRecord();

        return $record instanceof Model ? ImageMeta::allFor($record, $this->getName(), $this->getName()) : [];
    }

    /**
     * Bring the slots in line with the upload's current files and write them back:
     *
     * - the `n<key>` slot of a file that is stored now moves to its identifier slot, replacing
     *   whatever was there (those are the details typed for this upload);
     * - slots of files that are no longer in the upload are dropped, so a later file stored
     *   under the same path or uuid does not inherit them.
     *
     * Called after every change of the upload's state and before every write.
     *
     * @return array<string, mixed>
     */
    public function syncSlots(): array
    {
        $slots = $this->slots();
        $synced = $slots;
        $live = [];

        foreach ($this->getUpload()->getRawState() ?? [] as $key => $file) {
            $live[self::slotForKey($key)] = true;

            if (!is_string($file) || $file === '') {
                continue;
            }

            $identifier = self::slotForIdentifier($file);
            $live[$identifier] = true;

            if (array_key_exists(self::slotForKey($key), $synced)) {
                $synced[$identifier] = $synced[self::slotForKey($key)];
                unset($synced[self::slotForKey($key)]);
            }
        }

        $synced = array_intersect_key($synced, $live);

        if ($synced !== $slots) {
            $this->rawState($synced);
        }

        return $synced;
    }

    /**
     * Write the details into the custom properties of the record's media, once the upload has
     * saved its files (item key -> media uuid).
     */
    public function persistToMedia(): void
    {
        $record = $this->getUpload()->getRecord();

        if (!($record instanceof Model) || !($record instanceof HasMedia)) {
            return;
        }

        $slots = $this->syncSlots();
        $names = ImageMeta::mediaProperties();
        $media = [];

        foreach ($this->mediaOf($record) as $item) {
            $media[(string) $item->uuid] = $item;
        }

        foreach ($this->getUpload()->getRawState() ?? [] as $key => $uuid) {
            $item = is_string($uuid) ? ($media[$uuid] ?? null) : null;

            if (!$item instanceof Media) {
                continue;
            }

            $values = $this->options->limitTexts($this->lookup($slots, $key, $uuid), ImageMeta::forMedia($item))->toArray();

            foreach ([
                'alt' => 'alt',
                'decorative' => 'decorative',
                'caption' => 'caption',
                'title' => 'title',
                'focal' => 'focal_point',
            ] as $metaKey => $propertyKey) {
                if (array_key_exists($metaKey, $values)) {
                    $item->setCustomProperty($names[$propertyKey], $values[$metaKey]);
                } else {
                    $item->forgetCustomProperty($names[$propertyKey]);
                }
            }

            if ($item->isDirty()) {
                $item->save();
            }
        }
    }

    /**
     * The media items of the record that this upload currently holds.
     *
     * @return array<int, Media>
     */
    protected function mediaOf(Model&HasMedia $record): array
    {
        $uuids = array_values(array_filter($this->getUpload()->getRawState() ?? [], is_string(...)));

        return array_values(array_filter(
            $record->media()->whereIn('uuid', $uuids)->get()->all(),
            static fn (Model $item): bool => $item instanceof Media,
        ));
    }

    // ---------------------------------------------------------------- rendering

    /**
     * @return array<int, array{key: string, name: string, url: ?string, meta: ImageMeta, status: string}>
     */
    public function getRows(): array
    {
        $files = $this->getUpload()->getUploadedFiles() ?? [];
        $slots = $this->slots();
        $rows = [];

        foreach ($this->getUpload()->getRawState() ?? [] as $key => $file) {
            $info = $this->describe($file, $files[$key] ?? null);

            if ($info === null || !$info['image']) {
                continue;
            }

            $meta = $this->lookup($slots, $key, $file);

            $rows[] = [
                'key' => (string) $key,
                'name' => $info['name'],
                'url' => $info['url'],
                'meta' => $meta,
                'status' => $this->statusOf($meta),
            ];
        }

        return $rows;
    }

    /**
     * @param array{name?: string, size?: int, type?: ?string, url?: string}|null $uploaded
     *
     * @return array{name: string, url: ?string, image: bool}|null
     */
    protected function describe(mixed $file, ?array $uploaded): ?array
    {
        if ($file instanceof TemporaryUploadedFile) {
            return [
                'name' => $file->getClientOriginalName(),
                'url' => $file->isPreviewable() ? $file->temporaryUrl() : null,
                'image' => Str::startsWith((string) $file->getMimeType(), 'image/'),
            ];
        }

        if (!is_string($file) || $uploaded === null) {
            return null;
        }

        $type = $uploaded['type'] ?? null;
        $name = (string) ($uploaded['name'] ?? basename($file));

        $isImage = is_string($type) && $type !== ''
            ? Str::startsWith($type, 'image/')
            : in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp'], true);

        return ['name' => $name, 'url' => $uploaded['url'] ?? null, 'image' => $isImage];
    }

    /**
     * `decorative`, `ok`, `missing` (alt is collected and absent) or `none` (alt is not collected).
     */
    public function statusOf(ImageMeta $meta): string
    {
        if (!$this->options->alt) {
            return 'none';
        }

        if ($meta->isDecorative()) {
            return 'decorative';
        }

        foreach ($this->options->altLocalesRequired() as $locale) {
            if (!$meta->hasAlt($locale)) {
                return 'missing';
            }
        }

        return 'ok';
    }

    // ---------------------------------------------------------------- the modal

    public function getEditDetailsAction(): Action
    {
        return Action::make(self::ACTION)
            ->hidden(static fn (self $component): bool => $component->isDisabled() || $component->isHidden())
            ->label(__('image-meta::image-meta.edit_details'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->size(Size::Small)
            ->link()
            ->modalHeading(__('image-meta::image-meta.modal_heading'))
            ->modalWidth('2xl')
            ->modalSubmitActionLabel(__('image-meta::image-meta.apply'))
            ->fillForm(function (array $arguments, self $component): array {
                return $component->formDataFor($component->resolveKey($arguments));
            })
            ->schema(function (array $arguments, self $component): array {
                return $component->modalSchema($component->resolveKey($arguments));
            })
            ->action(function (array $data, array $arguments, self $component): void {
                $key = $component->resolveKey($arguments);
                $meta = $component->options->normalise($data);
                $file = ($component->getUpload()->getRawState() ?? [])[$key] ?? null;

                $candidates = self::slotsFor($key, $file);
                $slots = $component->slots();

                // One slot per file: drop every candidate (a stale `n<key>` slot left by a save in
                // this session included), then write the most specific one.
                foreach ($candidates as $candidate) {
                    unset($slots[$candidate]);
                }

                if (!$meta->isEmpty()) {
                    $slots[$candidates[0]] = $meta->toArray();
                }

                $component->rawState($slots === [] ? [] : $slots);
                $component->callAfterStateUpdated();
            });
    }

    /**
     * The item key from the action arguments, checked against the upload's current files:
     * the key comes from the browser.
     *
     * @param array<string, mixed> $arguments
     */
    public function resolveKey(array $arguments): string
    {
        $key = (string) ($arguments['key'] ?? '');

        abort_unless(array_key_exists($key, $this->getUpload()->getRawState() ?? []), 404);

        return $key;
    }

    /**
     * @return array<string, mixed>
     */
    public function formDataFor(string $key): array
    {
        $meta = $this->metaForKey($key);
        $o = $this->options;

        $localised = static function (array $texts) use ($o): array|string {
            if (!$o->isLocalised()) {
                return $texts[ImageMeta::ANY] ?? (string) Arr::first($texts, default: '');
            }

            $filled = [];

            foreach ($o->locales as $locale) {
                $filled[$locale] = $texts[$locale] ?? '';
            }

            // A language-less text from before `locales:` was added belongs to the first language.
            if (isset($texts[ImageMeta::ANY]) && $filled[$o->locales[0]] === '') {
                $filled[$o->locales[0]] = $texts[ImageMeta::ANY];
            }

            return $filled;
        };

        return [
            'alt' => $localised($meta->alt),
            'decorative' => $meta->decorative,
            'caption' => $localised($meta->caption),
            'title' => $localised($meta->title),
            'focal' => $meta->focal,
        ];
    }

    /**
     * @return array<int, Component>
     */
    public function modalSchema(string $key): array
    {
        $o = $this->options;
        $fields = [];

        if ($o->decorative && $o->alt) {
            $fields[] = Toggle::make('decorative')
                ->label(__('image-meta::image-meta.decorative'))
                ->helperText(__('image-meta::image-meta.decorative_helper'))
                ->live();
        }

        if ($o->alt) {
            $fields = [...$fields, ...$this->textFields('alt', $o->decorative)];
        }

        if ($o->caption) {
            $fields = [...$fields, ...$this->textFields('caption')];
        }

        if ($o->title) {
            $fields = [...$fields, ...$this->textFields('title')];
        }

        if ($o->focalPoint) {
            $fields[] = FocalPointPicker::make('focal')
                ->label(__('image-meta::image-meta.focal_point'))
                ->helperText(__('image-meta::image-meta.focal_point_helper'))
                ->imageUrl(fn (): ?string => $this->urlForKey($key));
        }

        return $fields;
    }

    protected function urlForKey(string $key): ?string
    {
        $row = Arr::first($this->getRows(), static fn (array $row): bool => $row['key'] === $key);

        return $row['url'] ?? null;
    }

    /**
     * One input, or one per language.
     *
     * @return array<int, TextInput>
     */
    protected function textFields(string $name, bool $disableWhenDecorative = false): array
    {
        $o = $this->options;
        $max = $name === 'alt' ? ImageMetaOptions::altMaxLength() : ImageMetaOptions::TEXT_MAX_LENGTH;
        $fields = [];

        $targets = $o->isLocalised()
            ? array_map(static fn (string $locale): array => [$name.'.'.$locale, $locale], $o->locales)
            : [[$name, null]];

        foreach ($targets as [$path, $locale]) {
            $field = TextInput::make($path)
                ->label(__('image-meta::image-meta.'.$name.($locale === null ? '' : '_locale'), ['locale' => $locale]))
                ->maxLength($max);

            if ($name === 'alt') {
                $field->helperText(__('image-meta::image-meta.alt_helper'));
            }

            if ($disableWhenDecorative) {
                $field->disabled(static fn (Get $get): bool => (bool) $get('decorative'));
            }

            $fields[] = $field;
        }

        return $fields;
    }
}
