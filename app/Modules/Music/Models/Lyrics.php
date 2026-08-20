<?php

namespace App\Modules\Music\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A track's lyrics (Database Specification §19's `lyrics` table) — one per
 * track. `visibility` (public/private) matches the approved listening
 * page's "View Full Lyrics" surface, which only ever shows for public
 * lyrics.
 */
#[Fillable(['track_id', 'content', 'visibility'])]
class Lyrics extends Model
{
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
