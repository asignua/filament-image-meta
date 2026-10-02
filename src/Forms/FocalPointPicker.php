<?php

declare(strict_types=1);

namespace Asignua\FilamentImageMeta\Forms;

use Asignua\FilamentImageMeta\ImageMeta;
use Closure;
use Filament\Forms\Components\Field;

/**
 * A picture with a draggable marker: the state is `{x, y}` in percent, or null (not set).
 * Usable on its own in any form; the "Edit details" modal puts one in.
 */
class FocalPointPicker extends Field
{
    protected string $view = 'image-meta::focal-point-picker';

    protected string|Closure|null $imageUrl = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Whatever arrives from the browser is pulled back into 0..100, or dropped.
        $this->dehydrateStateUsing(static fn (mixed $state): ?array => ImageMeta::focal($state));
    }

    public function imageUrl(string|Closure|null $url): static
    {
        $this->imageUrl = $url;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        $url = $this->evaluate($this->imageUrl);

        return is_string($url) && $url !== '' ? $url : null;
    }
}
