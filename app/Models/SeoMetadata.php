<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'meta_title', 'meta_description', 'keywords', 'canonical_url', 'robots',
    'og_title', 'og_description', 'twitter_title', 'twitter_description', 'structured_data',
])]
class SeoMetadata extends Model
{
    protected function casts(): array
    {
        return [
            'structured_data' => 'array',
        ];
    }

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_image_media_id');
    }

    public function twitterImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'twitter_image_media_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The single place "does this record's robots directive mean noindex?"
     * is decided — every Sitemapable content type's sitemapEntries() and
     * SeoTagBuilder alike defer to this rather than re-parsing the raw
     * robots string themselves (docs/development instructions for SEO.docx
     * §9: centralized, not scattered).
     */
    public function isNoindex(): bool
    {
        return str_contains(strtolower($this->robots ?? ''), 'noindex');
    }

    /**
     * True when this record's own SEO tab points its canonical URL
     * somewhere other than $selfUrl — i.e. it declares itself a
     * non-canonical duplicate of another page. A sitemap must never list a
     * "Non-canonical URL" under its own address (docs/development
     * instructions for SEO.docx §6), so every Sitemapable content type's
     * sitemapEntries() checks this before including itself.
     */
    public function canonicalPointsElsewhere(string $selfUrl): bool
    {
        if (blank($this->canonical_url)) {
            return false;
        }

        return rtrim($this->canonical_url, '/') !== rtrim($selfUrl, '/');
    }
}
