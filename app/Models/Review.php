<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Shared\Support\Reviews\Reviewable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A generic, reusable rating+review left by a registered user against any
 * "reviewable" model — the Podcast Episode page is the first consumer
 * (PodcastEpisode::reviews()), with Music and Inspirational Resources meant
 * to reuse this exact model/table later via the same polymorphic
 * reviewable_type/reviewable_id pair, never a per-module duplicate. See
 * App\Actions\Review for the shared submit/publish/reject workflow and
 * config('reviews.reviews_ratings_admin_approval') for the moderation
 * default.
 */
#[Fillable(['reviewable_type', 'reviewable_id', 'user_id', 'rating', 'content', 'status'])]
class Review extends Model
{
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
        ];
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Approved);
    }

    /**
     * A human label for the reviewed item, for the admin moderation table/
     * infolist — uses the Reviewable contract when the polymorphic model
     * implements it (every real consumer does), falling back to a generic
     * "Type #id" label so a moderation screen never breaks if it doesn't.
     */
    public function reviewableLabel(): string
    {
        $reviewable = $this->reviewable;

        if ($reviewable instanceof Reviewable) {
            return $reviewable->reviewTitle();
        }

        return class_basename($this->reviewable_type).' #'.$this->reviewable_id;
    }
}
