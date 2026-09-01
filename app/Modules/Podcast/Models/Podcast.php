<?php

namespace App\Modules\Podcast\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Media;
use App\Models\User;
use App\Modules\Podcast\Enums\PodcastStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The podcast show itself (Database Specification §5's `podcasts` table) —
 * the "About the Podcast" content on the approved Podcast List/Episode
 * designs. Episodes (podcast_episodes) belong to one of these.
 */
#[Fillable(['title', 'slug', 'description', 'artwork_media_id', 'status'])]
class Podcast extends Model
{
    use HasPublicId, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PodcastStatus::class,
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PodcastStatus::Published);
    }

    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'artwork_media_id');
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(PodcastEpisode::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
