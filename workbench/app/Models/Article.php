<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A model with media-library collections: the details live in each media item's custom properties.
 *
 * @property string|null $title
 */
class Article extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = ['*'];
}
