<?php

namespace App\Modules\Podcast\Models;

use App\Models\Category;
use App\Models\Concerns\HasPublicId;
use App\Models\Media;
use App\Models\Review;
use App\Models\SeoMetadata;
use App\Models\Tag;
use App\Models\User;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Shared\Support\Reviews\Reviewable;
use App\Shared\Support\Seo\Sitemapable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * An episode of a Podcast (Database Specification §5's `podcast_episodes`
 * table) — the approved Podcast List/Episode designs' individual episode
 * content. season/episode_number are nullable ("where applicable" — a
 * bonus/one-off episode may have neither).
 */
#[Fillable([
    'podcast_id', 'title', 'slug', 'description', 'artwork_media_id',
    'publish_date', 'season', 'episode_number', 'embed_url', 'audio_media_id', 'video_media_id',
    'duration_seconds', 'status', 'publish_at',
])]
class PodcastEpisode extends Model implements Reviewable, Sitemapable
{
    use HasPublicId, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PodcastEpisodeStatus::class,
            'publish_date' => 'date',
            'publish_at' => 'datetime',
            'duration_seconds' => 'integer',
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

    /**
     * The privately-stored uploaded audio file, if this episode has one —
     * distinct from `embed_url`, which is always an external streaming
     * reference. See the add_audio_media_id migration's docblock.
     */
    public function audio(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'audio_media_id');
    }

    /**
     * The privately-stored uploaded video file, if this episode has one —
     * distinct from `embed_url`, which is always an external reference. See
     * the add_video_media_id migration's docblock.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'video_media_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(PodcastLink::class)->orderBy('sort_order');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'podcast_categories');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'podcast_tags');
    }

    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function reviewTitle(): string
    {
        return $this->title;
    }

    public function reviewUrl(): string
    {
        return route('podcast.episodes.show', $this);
    }

    /**
     * The video ID parsed out of `embed_url` (YouTube only — the reference
     * design's only supported provider) — shared by thumbnailUrl() below and
     * PodcastController's iframe-embed builder, so the parsing regex lives
     * in exactly one place.
     */
    public function youtubeVideoId(): ?string
    {
        if (blank($this->embed_url)) {
            return null;
        }

        if (preg_match('#youtu\.be/([\w-]+)#', $this->embed_url, $m)
            || preg_match('#youtube\.com/(?:watch\?v=|embed/|shorts/)([\w-]+)#', $this->embed_url, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * The best thumbnail available for this episode: the admin's own
     * uploaded artwork when set, otherwise automatically derived from the
     * configured YouTube video — never a fabricated placeholder image, and
     * never requiring the admin to separately upload a thumbnail just to
     * duplicate what YouTube already has.
     */
    public function thumbnailUrl(): ?string
    {
        if ($this->artwork) {
            return Storage::disk($this->artwork->disk)->url($this->artwork->path);
        }

        $videoId = $this->youtubeVideoId();

        return $videoId ? "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg" : null;
    }

    /**
     * Mirrors PoetryProse::sitemapEntries()/Page::sitemapEntries() exactly —
     * published scope (episode AND its parent Podcast show), reject noindex/
     * canonical-elsewhere, then map to loc/lastmod. Fully public, no
     * membership/entitlement gate on viewing.
     *
     * @return Collection<int, array{loc: string, lastmod: mixed}>
     */
    public static function sitemapEntries(): Collection
    {
        return static::query()
            ->published()
            ->whereHas('podcast', fn (Builder $query) => $query->where('status', PodcastStatus::Published))
            ->with('seo')
            ->orderBy('slug')
            ->get()
            ->reject(fn (self $episode): bool => ($episode->seo?->isNoindex() ?? false)
                || ($episode->seo?->canonicalPointsElsewhere(route('podcast.episodes.show', $episode)) ?? false))
            ->map(fn (self $episode): array => [
                'loc' => route('podcast.episodes.show', $episode),
                'lastmod' => $episode->updated_at,
            ])
            ->values();
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
