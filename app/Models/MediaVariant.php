<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single derived rendition (thumbnail or responsive width, in one image
 * format) of an image Media row — never the original upload itself, which
 * stays untouched on the owning Media record (ADMIN-005).
 */
#[Fillable(['media_id', 'type', 'width', 'height', 'format', 'disk', 'path', 'size'])]
class MediaVariant extends Model
{
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
