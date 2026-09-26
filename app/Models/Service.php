<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * One service (Admin → Services), public at /services/{slug} while
 * published. Featured services also appear in the homepage's Services
 * section (PageSectionType::Services).
 *
 * highlights: [{title, description}] — "What's included"
 * technologies: [string]
 * faqs: [{question, answer}] — rendered with FAQPage structured data
 */
#[Fillable([
    'title', 'slug', 'icon', 'tagline', 'summary', 'body', 'cover_media_id', 'highlights',
    'technologies', 'faqs', 'is_featured', 'is_published', 'sort_order', 'created_by', 'updated_by',
])]
class Service extends Model
{
    protected function casts(): array
    {
        return [
            'highlights' => 'array',
            'technologies' => 'array',
            'faqs' => 'array',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
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
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function url(): string
    {
        return route('services.show', $this);
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
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
