<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A single 🙌 left by a registered user against any "reactable" model
 * (Track, PodcastEpisode, PoetryProse) — fully independent of
 * App\Models\Review (client-confirmed, 2026-09-02: a reaction is never a
 * repurposed star rating, and never shares a row with a comment). No
 * moderation, no status column: unlike a Review's written content, a
 * single-tap reaction carries no free-text spam/abuse surface, so it's
 * never queued for admin approval — see App\Actions\Reaction\
 * ToggleReactionAction for the only way a row here is ever created or
 * removed.
 */
#[Fillable(['reactable_type', 'reactable_id', 'user_id'])]
class Reaction extends Model
{
    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
