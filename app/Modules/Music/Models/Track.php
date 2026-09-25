<?php

namespace App\Modules\Music\Models;

use App\Models\Category;
use App\Models\Concerns\HasPublicId;
use App\Models\Media;
use App\Models\Reaction;
use App\Models\Review;
use App\Models\SeoMetadata;
use App\Models\Tag;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Exceptions\InvalidTrackReleaseException;
use App\Shared\Support\Reviews\Reviewable;
use App\Shared\Support\Search\SearchResultRepresentable;
use App\Shared\Support\Seo\Sitemapable;
use App\Shared\Support\Text\PlainText;
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
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;

/**
 * One song (Database Specification §19's `tracks` table) — belongs to an
 * Album, a Single, or both at once (album_id/single_id are both nullable;
 * the same song can be released as a Single and also appear on an Album).
 * The Filament form and Create/UpdateTrackAction (see
 * SavesTrackRelations::resolveRelease()) enforce "at least one" at the
 * UI/Action layer; booted() below is the model-level backstop that fires on
 * every save regardless of caller (Tinker, seeders, jobs, future imports),
 * and a MariaDB-only CHECK constraint backs it at the raw-SQL layer — see
 * InvalidTrackReleaseException's docblock. Carries the approved
 * listening-page design's per-song content: description, lyrics, song
 * story, credits, genre/tags, and the Details panel fields.
 */
#[Fillable([
    'album_id', 'single_id', 'title', 'slug', 'description', 'track_number', 'duration_seconds',
    'written_by', 'produced_by', 'isrc', 'video_embed_url', 'audio_media_id', 'video_media_id',
    'embed_video_url', 'status',
])]
class Track extends Model implements Reviewable, SearchResultRepresentable, Sitemapable
{
    use HasPublicId, Searchable, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Track $track): void {
            if ($track->album_id === null && $track->single_id === null) {
                throw InvalidTrackReleaseException::mustBelongToAtLeastOne();
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
     * `$track->album ?: $track->single`. The Album wins when the track is on
     * both. Returns the already-loaded relation when eager-loaded, otherwise
     * lazy-loads whichever side is set. Never duplicates Album/Single data
     * onto Track — this only resolves to the existing related record.
     */
    public function release(): Album|Single|null
    {
        return $this->album ?: $this->single;
    }

    /**
     * Like release(), but only a Published parent counts — a track on both
     * an unpublished Album and a published Single is still publicly
     * reachable through the Single. The Album wins when both are published,
     * matching MusicController::showTrack()'s canonical-URL rule.
     */
    public function publishedRelease(): Album|Single|null
    {
        if ($this->album?->status === ReleaseStatus::Published) {
            return $this->album;
        }

        return $this->single?->status === ReleaseStatus::Published ? $this->single : null;
    }

    /**
     * The one public URL this song lives at: its own track page while its
     * Album is published, otherwise its Single's page — showTrack()
     * 301-redirects there in that case rather than publishing the same song
     * at two URLs.
     */
    public function publicUrl(): string
    {
        return $this->publishedRelease() instanceof Single
            ? route('music.singles.show', $this->single)
            : route('music.tracks.show', $this);
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

    // `embed_video_url` (plain string column, no accessor needed) is a
    // second, independent external video URL — mirrors albums.embed_video_url
    // (YouTube only) and drives the "Watch Video" button placed just before
    // the Share buttons on the listening page. Distinct from `video_embed_url`
    // above, which stays scoped to the Song Story section's own "Watch video"
    // icon. See the add_embed_video_url_to_tracks_table migration.

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

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function reviewTitle(): string
    {
        return $this->title;
    }

    /**
     * Reviews show on whichever page the song publicly lives at — see
     * publicUrl().
     */
    public function reviewUrl(): string
    {
        return $this->publicUrl();
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

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
        ];
    }

    /**
     * The real enforcement point for Scout's "database" driver (see
     * config('scout.driver')): DatabaseEngine::searchModels() queries this
     * method's return value directly rather than a separate synced index,
     * so shouldBeSearchable() below is never consulted for this driver (it
     * only matters if a future engine swap introduces a real remote index —
     * kept in sync so nothing else has to change if that happens).
     *
     * whereNotNull('album_id') is the canonical-URL rule confirmed against
     * showTrack() above: a single-owned track 301-redirects to its Single's
     * own page rather than publishing the same song at two URLs, so it must
     * never surface as its own search result either — its content is
     * already covered by indexing the Single. Mirrors sitemapEntries()'s
     * own whereNotNull('album_id') constraint exactly, for the same reason.
     */
    public function newScoutQuery(ScoutBuilder $builder): Builder
    {
        return static::query()->published()->whereNotNull('album_id');
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === TrackStatus::Published && $this->album_id !== null;
    }

    public function searchResultType(): string
    {
        return 'Music';
    }

    public function searchResultTitle(): string
    {
        return $this->title;
    }

    public function searchResultExcerpt(): string
    {
        return $this->description
            ? str(PlainText::fromHtml($this->description))->limit(160)->toString()
            : '';
    }

    /**
     * A Track has no cover of its own — it always shows its parent Album's
     * (a single-owned track never reaches here at all, per newScoutQuery()
     * above).
     */
    public function searchResultImageUrl(): ?string
    {
        $cover = $this->album?->cover;

        return $cover ? Storage::disk($cover->disk)->url($cover->path) : null;
    }

    public function searchResultUrl(): string
    {
        return route('music.tracks.show', $this);
    }
}
