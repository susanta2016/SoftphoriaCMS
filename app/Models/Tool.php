<?php

namespace App\Models;

use App\Enums\ToolStatus;
use App\Tools\ToolFunctionality;
use App\Tools\ToolRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * One tool landing page (Admin → Tools): content, SEO, CTA and publication
 * state. The working tool itself is the application-code functionality
 * named by `functionality` (see App\Tools\ToolRegistry) — editing this row
 * never touches that code, and deploying new code never touches this row.
 *
 * Public only while live(): Published AND its functionality exists in this
 * deployment — so a tool whose code was removed disappears from the site
 * instead of rendering a broken page.
 */
#[Fillable([
    'name', 'slug', 'tool_category_id', 'functionality', 'is_featured', 'sort_order',
    'short_description', 'icon', 'heading', 'introduction', 'how_it_works', 'use_cases',
    'additional_content', 'important_notes', 'service_id', 'cta_heading', 'cta_text',
    'cta_label', 'cta_url', 'created_by', 'updated_by',
])]
class Tool extends Model
{
    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'status' => ToolStatus::class,
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Publicly visible: published, with a functionality this deployment has.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', ToolStatus::Published->value)
            ->whereIn('functionality', app(ToolRegistry::class)->keys());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    public function isLive(): bool
    {
        return $this->status === ToolStatus::Published && $this->functionalityModule() !== null;
    }

    public function functionalityModule(): ?ToolFunctionality
    {
        return app(ToolRegistry::class)->find($this->functionality);
    }

    public function url(): string
    {
        return route('tools.show', $this);
    }

    public function previewUrl(): string
    {
        return route('tools.preview', $this);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ToolCategory::class, 'tool_category_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(ToolFaq::class)->orderBy('sort_order')->orderBy('id');
    }

    public function relatedTools(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'tool_related', 'tool_id', 'related_tool_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function redirects(): HasMany
    {
        return $this->hasMany(ToolRedirect::class);
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
