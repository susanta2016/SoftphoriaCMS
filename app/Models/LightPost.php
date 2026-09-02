<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Shared\Support\Search\SearchResultRepresentable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;

/**
 * A member-authored short public message — first captured by the
 * registration page's "Leave a Little Light ✨" prompt (RegisterFreeUserAction/
 * RegisterProUserAction, via CreatesLightPostOnRegistration). A private
 * (non-public) Light Post is not shown anywhere. A public one now also has
 * its own minimal detail page (LightPostController@show,
 * light-posts.show) — added only so unified Search (see App\Modules\Search)
 * has a canonical URL to link to; it is deliberately NOT registered in
 * config('seo.sitemap_sources') (see that config file's own comment, which
 * names Light Post explicitly) — pages here are reachable directly and via
 * Search, not promoted for organic indexing, hence ROBOTS_NOINDEX in
 * LightPostController rather than Sitemapable.
 */
#[Fillable(['user_id', 'content', 'is_public'])]
class LightPost extends Model implements SearchResultRepresentable
{
    use HasPublicId, Searchable;

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'content' => $this->content,
        ];
    }

    /**
     * Laravel Scout's "database" engine (config('scout.driver')) never
     * consults shouldBeSearchable() — it runs a live query against this
     * method's return value instead (see Laravel\Scout\Engines\
     * DatabaseEngine::newSearchQuery()), so this is the real enforcement
     * point keeping a private Light Post out of search results, not the
     * shouldBeSearchable() override below. Both are kept in sync so nothing
     * changes if the driver is ever swapped for a real remote index later.
     */
    public function newScoutQuery(ScoutBuilder $builder): Builder
    {
        return static::query()->public();
    }

    public function shouldBeSearchable(): bool
    {
        return $this->is_public;
    }

    public function searchResultType(): string
    {
        return 'Community';
    }

    /**
     * A Light Post has no title of its own (see the migration's docblock:
     * "member-authored short public message") — falls back to attributing
     * it to the author, the same "no blank title" convention
     * ResourceSubmission::publicTitle() already uses.
     */
    public function searchResultTitle(): string
    {
        return $this->user?->name ? "A Little Light from {$this->user->name}" : 'A Little Light';
    }

    public function searchResultExcerpt(): string
    {
        return str($this->content)->limit(160)->toString();
    }

    /**
     * No image on this model — a Light Post is text-only.
     */
    public function searchResultImageUrl(): ?string
    {
        return null;
    }

    public function searchResultUrl(): string
    {
        return route('light-posts.show', $this);
    }
}
