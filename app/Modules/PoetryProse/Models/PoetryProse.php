<?php

namespace App\Modules\PoetryProse\Models;

use App\Models\Category;
use App\Models\Concerns\HasPublicId;
use App\Models\Media;
use App\Models\SeoMetadata;
use App\Models\Tag;
use App\Models\User;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Modules\PoetryProse\Enums\PoetryProseContentType;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Shared\Support\Seo\Sitemapable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

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
class PoetryProse extends Model implements Sitemapable
{
    use HasPublicId, SoftDeletes;

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

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
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

    /**
     * The submission this entry was drafted from, if any — the FK
     * (resource_submissions.poetry_prose_id) lives on the submission side,
     * set only by CreatePoetryProseFromSubmissionAction and never written
     * back to afterward.
     */
    public function sourceSubmission(): HasOne
    {
        return $this->hasOne(ResourceSubmission::class, 'poetry_prose_id');
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
}
