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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * A one-song release (Database Specification §19's `singles` table). Unlike
 * an Album, a Single has exactly one Track (tracks.single_id) carrying its
 * lyrics/song story/credits — see Master Scope Specification §8.1: "Single:
 * title, artwork, description and streaming links", with the song content
 * itself modelled the same way an album's song is.
 */
#[Fillable([
    'title', 'slug', 'release_date', 'description', 'cover_media_id',
    'status', 'publish_at', 'is_featured',
])]
class Single extends Model implements Sitemapable
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

    public function track(): HasOne
    {
        return $this->hasOne(Track::class);
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
            ->reject(fn (self $single): bool => ($single->seo?->isNoindex() ?? false)
                || ($single->seo?->canonicalPointsElsewhere(route('music.singles.show', $single)) ?? false))
            ->map(fn (self $single): array => [
                'loc' => route('music.singles.show', $single),
                'lastmod' => $single->updated_at,
            ])
            ->values();
    }
}
