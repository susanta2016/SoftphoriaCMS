<?php

namespace App\Modules\Music\Models;

use App\Models\Category;
use App\Models\Concerns\HasPublicId;
use App\Models\Media;
use App\Models\Review;
use App\Models\SeoMetadata;
use App\Models\Tag;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Exceptions\InvalidTrackReleaseException;
use App\Shared\Support\Reviews\Reviewable;
use App\Shared\Support\Seo\Sitemapable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * One song (Database Specification §19's `tracks` table) — belongs to
 * exactly one of an Album or a Single (album_id/single_id are both
 * nullable). The Filament form and Create/UpdateTrackAction (see
 * SavesTrackRelations::resolveRelease()) enforce "exactly one" at the
 * UI/Action layer; booted() below is the model-level backstop that fires on
 * every save regardless of caller (Tinker, seeders, jobs, future imports),
 * and a MariaDB-only CHECK constraint backs it at the raw-SQL layer — see
 * InvalidTrackReleaseException's docblock. Carries the approved
 * listening-page design's per-song content: description, lyrics, song
 * story, credits, genre/tags, and the Details panel fields.
 */
#[Fillable([
    'album_id', 'single_id', 'title', 'slug', 'description', 'track_number', 'duration_seconds',
    'written_by', 'produced_by', 'isrc', 'video_embed_url', 'audio_media_id', 'video_media_id', 'status',
])]
class Track extends Model implements Reviewable, Sitemapable
{
    use HasPublicId, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Track $track): void {
            $hasAlbum = $track->album_id !== null;
            $hasSingle = $track->single_id !== null;

            if ($hasAlbum === $hasSingle) {
                throw InvalidTrackReleaseException::mustBelongToExactlyOne();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => TrackStatus::class,
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', TrackStatus::Published);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function single(): BelongsTo
    {
        return $this->belongsTo(Single::class);
    }

    /**
     * The track's parent release, whichever type it is — the single
     * accessor future listening-page code should call instead of repeating
     * `$track->album ?: $track->single`. Returns the already-loaded relation
     * when eager-loaded, otherwise lazy-loads whichever side is set. Never
     * duplicates Album/Single data onto Track — this only resolves to the
     * existing related record.
     */
    public function release(): Album|Single|null
    {
        return $this->album ?: $this->single;
    }

    /**
     * @return 'album'|'single'|null
     */
    public function releaseType(): ?string
    {
        return match (true) {
            $this->album_id !== null => 'album',
            $this->single_id !== null => 'single',
            default => null,
        };
    }

    public function audio(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'audio_media_id');
    }

    /**
     * The privately-stored uploaded video file, if this track has one —
     * distinct from `video_embed_url`, which is always an external
     * reference (YouTube/Vimeo). See the add_video_media_id migration.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'video_media_id');
    }

    public function lyrics(): HasOne
    {
        return $this->hasOne(Lyrics::class);
    }

    public function songStory(): HasOne
    {
        return $this->hasOne(SongStory::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(TrackCredit::class)->orderBy('sort_order');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'music_categories');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'music_tags');
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

    /**
     * A Single-owned track's reviews live on its Single's own listening
     * page (there is no separate public page for the track itself — see
     * MusicController::showTrack()'s redirect); an Album-owned track's
     * reviews live on its own track page.
     */
    public function reviewUrl(): string
    {
        return $this->single_id !== null
            ? route('music.singles.show', $this->single)
            : route('music.tracks.show', $this);
    }

    /**
     * Only Album-owned tracks get their own sitemap entry — a Single-owned
     * track 301-redirects to that Single's own page (MusicController::
     * showTrack()) rather than publishing the same song at two URLs, so it
     * must never appear here too (a sitemap listing a non-canonical URL is
     * exactly the contradiction Sitemapable's docblock warns against).
     *
     * @return Collection<int, array{loc: string, lastmod: mixed}>
     */
    public static function sitemapEntries(): Collection
    {
        return static::query()
            ->published()
            ->whereNotNull('album_id')
            ->whereHas('album', fn (Builder $query) => $query->where('status', ReleaseStatus::Published->value))
            ->with(['seo', 'album'])
            ->orderBy('slug')
            ->get()
            ->reject(fn (self $track): bool => ($track->seo?->isNoindex() ?? false)
                || ($track->seo?->canonicalPointsElsewhere(route('music.tracks.show', $track)) ?? false))
            ->map(fn (self $track): array => [
                'loc' => route('music.tracks.show', $track),
                'lastmod' => $track->updated_at,
            ])
            ->values();
    }
}
