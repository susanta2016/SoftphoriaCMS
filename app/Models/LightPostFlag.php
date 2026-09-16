<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single 🚩 report left by a registered user against a specific
 * App\Models\LightPost (Gratitude Journal shared-feed entry) — see that
 * model's own docblock, and App\Models\ReviewFlag for the equivalent on a
 * Review comment. Never polymorphic: it always targets a LightPost row.
 * See App\Actions\GratitudeJournal\FlagGratitudeJournalEntryAction for the
 * only way a row here is ever created.
 */
#[Fillable(['light_post_id', 'user_id'])]
class LightPostFlag extends Model
{
    public function lightPost(): BelongsTo
    {
        return $this->belongsTo(LightPost::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
