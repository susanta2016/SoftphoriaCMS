<?php

namespace App\Modules\Podcast\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Media;
use App\Models\SeoMetadata;
use App\Models\User;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An episode of a Podcast (Database Specification §5's `podcast_episodes`
 * table) — the approved Podcast List/Episode designs' individual episode
 * content. season/episode_number are nullable ("where applicable" — a
 * bonus/one-off episode may have neither).
 */
#[Fillable([
    'podcast_id', 'title', 'slug', 'description', 'artwork_media_id',
    'publish_date', 'season', 'episode_number', 'embed_url', 'status', 'publish_at',
])]
class PodcastEpisode extends Model
{
    use HasPublicId, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PodcastEpisodeStatus::class,
            'publish_date' => 'date',
            'publish_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PodcastEpisodeStatus::Published);
    }

    public function podcast(): BelongsTo
    {
        return $this->belongsTo(Podcast::class);
    }

    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'artwork_media_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(PodcastLink::class)->orderBy('sort_order');
    }

    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
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
