<?php

namespace App\Modules\PoetryProse\Models;

use App\Models\Category;
use App\Models\Concerns\HasPublicId;
use App\Models\Media;
use App\Models\Reaction;
use App\Models\Review;
use App\Models\SeoMetadata;
use App\Models\Tag;
use App\Models\User;
use App\Modules\PoetryProse\Enums\PoetryProseContentType;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Shared\Support\Reviews\Reviewable;
use App\Shared\Support\Search\SearchResultRepresentable;
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
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;

/**
 * One editorial piece — Essay, Reflection, Hymn, Poem, or Article
 * (database/migrations/2026_08_10_100801_create_poetry_prose_table.php).
 * Fully public once Published (client-confirmed: Pro Membership never
 * gates viewing in this module) — the only gating this model's own
 * lifecycle expresses is `status`/`publish_at`, exactly like Page/Album/
 * PodcastEpisode.
 */
#[Fillable([
    'title', 'slug', 'body', 'content_type', 'collection_id', 'featured_image_id',
    'status', 'publish_at', 'author_id',
])]
class PoetryProse extends Model implements Reviewable, SearchResultRepresentable, Sitemapable
{
    use HasPublicId, Searchable, SoftDeletes;

    /**
     * The migrated table (2026_08_10_100801_create_poetry_prose_table.php)
     * is deliberately singular — Eloquent's default pluralization would
     * otherwise guess "poetry_proses".
     */
    protected $table = 'poetry_prose';

    protected function casts(): array
    {
        return [
            'status' => PoetryProseStatus::class,
            'content_type' => PoetryProseContentType::class,
            'publish_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PoetryProseStatus::Published);
    }

    /**
     * Plain-text LIKE search across title and body, same convention as
     * PodcastController::episodes()'s title/description search — no
     * separate search index/service introduced for this module.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('title', 'like', "%{$term}%")
            ->orWhere('body', 'like', "%{$term}%"));
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    public function featuredImageUrl(): ?string
    {
        $media = $this->featuredImage;

        return $media ? Storage::disk($media->disk)->url($media->path) : null;
    }

    /**
     * Derived, never stored — same "computed from real content, not
     * hardcoded in Blade" approach the SEO description already uses
     * (see PoetryProseController). Kept here so index/show views share one
     * implementation instead of duplicating the str()->stripTags() call.
     */
    public function excerpt(int $length = 160): string
    {
        return str($this->plainTextBody())->limit($length)->toString();
    }

    /**
     * No stored reading-time field exists on this model — computed from the
     * real body word count (200 wpm, same standard estimate used
     * industry-wide) rather than inventing a static value per entry.
     */
    public function readingTimeMinutes(): int
    {
        $words = str($this->plainTextBody())->explode(' ')->filter()->count();

        return max(1, (int) ceil($words / 200));
    }

    /**
     * The RichEditor-authored body is HTML with entities (e.g. "&mdash;")
     * — stripTags() alone leaves those entities showing up literally in
     * plain-text contexts (excerpts, reading-time word counts), so they're
     * decoded back to real characters here.
     */
    private function plainTextBody(): string
    {
        return html_entity_decode(str($this->body)->stripTags()->squish()->toString(), ENT_QUOTES | ENT_HTML5);
    }

    /**
     * The credited byline — distinct from createdBy()/updatedBy() below,
     * which record whichever admin actually typed the entry into Filament.
     * Nullable: not every piece is attributed to a specific person.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'poetry_prose_categories');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'poetry_prose_tags');
    }

    /**
     * One collection per entry (client-confirmed, final) via a simple
     * belongsTo — the schema also happens to provision a many-to-many
     * poetry_prose_collection_items pivot, but that is left unused rather
     * than wired up, per the client's explicit "do not implement
     * many-to-many collections" instruction.
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(PoetryProseCollection::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PoetryProseRevision::class)->orderByDesc('version');
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

    public function reviewUrl(): string
    {
        return route('poetry-prose.show', $this);
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
     * Mirrors Page::sitemapEntries() exactly (docs/development instructions
     * for SEO.docx §1/§6/§9) — published scope, then reject noindex/
     * canonical-elsewhere, then map to loc/lastmod. Fully public per the
     * confirmed membership decision, so no additional access check here.
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
            ->reject(fn (self $entry): bool => ($entry->seo?->isNoindex() ?? false)
                || ($entry->seo?->canonicalPointsElsewhere(route('poetry-prose.show', $entry)) ?? false))
            ->map(fn (self $entry): array => [
                'loc' => route('poetry-prose.show', $entry),
                'lastmod' => $entry->updated_at,
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
            'body' => $this->body,
        ];
    }

    /**
     * See Track::newScoutQuery()'s docblock — this is the real enforcement
     * point for Scout's "database" driver, not shouldBeSearchable() below.
     */
    public function newScoutQuery(ScoutBuilder $builder): Builder
    {
        return static::query()->published();
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === PoetryProseStatus::Published;
    }

    public function searchResultType(): string
    {
        return 'Poetry / Prose';
    }

    public function searchResultTitle(): string
    {
        return $this->title;
    }

    public function searchResultExcerpt(): string
    {
        return $this->excerpt();
    }

    public function searchResultImageUrl(): ?string
    {
        return $this->featuredImageUrl();
    }

    public function searchResultUrl(): string
    {
        return route('poetry-prose.show', $this);
    }
}
