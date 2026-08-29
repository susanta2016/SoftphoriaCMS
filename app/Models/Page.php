<?php

namespace App\Models;

use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Models\Concerns\HasPublicId;
use App\Shared\Support\Seo\Sitemapable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['title', 'slug', 'template', 'status', 'summary', 'publish_at', 'featured_image_id'])]
class Page extends Model implements Sitemapable
{
    use HasPublicId, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'template' => PageTemplate::class,
            'publish_at' => 'datetime',
        ];
    }

    /**
     * The only query ADMIN-007 Navigation is allowed to run against Pages —
     * a `type=page` menu item may only reference a currently-published page
     * (ADMIN-006 §H). No relation exists in the other direction.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PageStatus::Published);
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class);
    }

    public function faqItems(): HasMany
    {
        return $this->hasMany(PageFaqItem::class);
    }

    public function redirects(): HasMany
    {
        return $this->hasMany(PageRedirect::class);
    }

    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }

    /**
     * The "home" slug is excluded: PageController permanently redirects it
     * to "/", so HomeController::sitemapEntries() is the one place that URL
     * is represented — otherwise it would appear in the sitemap twice, at
     * both "/" and "/home". "music" is excluded for the same reason: it's
     * only ever a content/Hero-banner source consumed by MusicController
     * (see its own private musicPage()) — the Music module's own routes (registered
     * ahead of the {page:slug} catch-all in routes/web.php) always win at
     * that URL, so MusicController::sitemapEntries() is the one place "/music"
     * is represented, not this one.
     */
    public static function sitemapEntries(): Collection
    {
        return static::query()
            ->published()
            ->whereNotIn('slug', ['home', 'music'])
            ->with('seo')
            ->orderBy('slug')
            ->get()
            ->reject(fn (self $page): bool => ($page->seo?->isNoindex() ?? false)
                || ($page->seo?->canonicalPointsElsewhere(route('pages.show', $page)) ?? false))
            ->map(fn (self $page): array => [
                'loc' => route('pages.show', $page),
                'lastmod' => $page->updated_at,
            ])
            ->values();
    }
}
