<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single 🚩 report left by a registered user against a specific
 * App\Models\Review (comment) — see that model's own docblock. Never
 * polymorphic itself: it always targets a Review row, regardless of which
 * module (Podcast, Music, Poetry/Prose) the reviewed item belongs to. See
 * App\Actions\Review\FlagReviewAction for the only way a row here is ever
 * created.
 */
#[Fillable(['review_id', 'user_id'])]
class ReviewFlag extends Model
{
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
