<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A model with plain FileUpload columns: `photo` (one file), `gallery` (many), and a JSON
 * sibling for the details of each.
 *
 * @property string|null $title
 * @property string|null $photo
 * @property array<string, mixed>|null $photo_meta
 * @property array<int, string>|null $gallery
 * @property array<string, mixed>|null $gallery_meta
 * @property array<int|string, mixed>|null $blocks
 */
class Post extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'photo_meta' => 'array',
            'gallery' => 'array',
            'gallery_meta' => 'array',
            'blocks' => 'array',
        ];
    }
}
