<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One portfolio project, managed in Admin → Portfolio. Published items are
 * listed at /portfolio and each has its own detail page at
 * /portfolio/{slug}; published + featured items are also shown by the
 * homepage's Featured Portfolio section (PageSectionType::Portfolio,
 * resources/views/components/site/blocks/portfolio.blade.php).
 *
 * challenge / solution / outcome: optional rich text, shown on the detail
 * page only when filled in — never generated.
 * gallery_media_ids: optional Media Library image ids, in display order.
 * link_url: the optional public project URL ("Visit project").
 */
#[Fillable([
    'title', 'slug', 'category', 'summary', 'challenge', 'solution', 'outcome', 'cover_media_id', 'gallery_media_ids',
    'icon', 'link_url', 'technologies', 'is_featured', 'is_published', 'sort_order', 'created_by', 'updated_by',
])]
class PortfolioItem extends Model
{
    protected static function booted(): void
    {
        // Every project needs a public URL segment; callers that don't
        // pass one (seeders, quick admin/test inserts) get one from the title.
        static::creating(function (self $item): void {
            if (blank($item->slug)) {
                $item->slug = self::uniqueSlug($item->title);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'technologies' => 'array',
            'gallery_media_ids' => 'array',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public static function uniqueSlug(?string $title): string
    {
        $base = Str::slug((string) $title) ?: 'project';
        $slug = $base;

        for ($i = 2; self::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function url(): string
    {
        return route('portfolio.show', ['item' => $this->slug]);
    }

    /**
     * The optional project URL, opened in a new tab when it's off-site.
     */
    public function isExternalLink(): bool
    {
        return filled($this->link_url) && str_starts_with($this->link_url, 'http');
    }

    /**
     * Gallery images that still exist in the Media Library, in the order
     * the admin chose.
     *
     * @return Collection<int, Media>
     */
    public function galleryMedia(): Collection
    {
        $ids = array_values(array_filter(array_map('intval', $this->gallery_media_ids ?? [])));

        if ($ids === []) {
            return collect();
        }

        $media = Media::query()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn (int $id): ?Media => $media->get($id))->filter()->values();
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->orderBy('services.sort_order')->orderBy('services.id');
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
