<?php

namespace App\Modules\Music\Models;

use App\Models\Media;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The "Song Story" behind a track (Database Specification §19's
 * `song_stories` table) — one per track, with an optional accompanying
 * image, matching the approved listening page's Song Story panel.
 */
#[Fillable(['track_id', 'content', 'media_id'])]
class SongStory extends Model
{
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
