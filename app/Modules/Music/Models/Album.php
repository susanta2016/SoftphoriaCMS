<?php

namespace App\Modules\Music\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Media;
use App\Models\SeoMetadata;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Shared\Support\Seo\Sitemapable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * A multi-track release (Database Specification §19's `albums` table) — the
 * approved Music page layout's "Featured Release"/catalogue content. Tracks
 * belong to one of these via tracks.album_id.
 */
#[Fillable([
    'title', 'slug', 'release_date', 'description', 'cover_media_id', 'embed_video_url',
    'status', 'publish_at', 'is_featured',
])]
class Album extends Model implements Sitemapable
{
    use HasPublicId, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ReleaseStatus::class,
            'release_date' => 'date',
            'publish_at' => 'datetime',
            'is_featured' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ReleaseStatus::Published);
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class)->orderBy('track_number');
    }

    public function streamingLinks(): HasMany
    {
        return $this->hasMany(MusicStreamingLink::class)->orderBy('sort_order');
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

    /**
     * Mirrors Page::sitemapEntries() exactly: published scope, then reject
     * noindex/non-canonical, then map to loc/lastmod.
     *
     * @return Collection<int, array{loc: string, lastmod: mixed}>
     */
    public static function sitemapEntries(): Collection
    {
        return static::query()
            ->published()
            ->with('seo')
            ->orderBy('slug')
            ->get()
            ->reject(fn (self $album): bool => ($album->seo?->isNoindex() ?? false)
                || ($album->seo?->canonicalPointsElsewhere(route('music.albums.show', $album)) ?? false))
            ->map(fn (self $album): array => [
                'loc' => route('music.albums.show', $album),
                'lastmod' => $album->updated_at,
            ])
            ->values();
    }
}
