<?php

namespace App\Modules\Music\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One role/name credit line for a track (e.g. "Vocals, Lyrics, Composition"
 * -> "IAWARII"), matching the approved listening page's Credits panel.
 */
#[Fillable(['track_id', 'role', 'name', 'sort_order'])]
class TrackCredit extends Model
{
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
