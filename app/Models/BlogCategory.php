<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A blog category (Admin → Blog → Categories). Each post has at most one;
 * each category has an indexable archive at /blog/category/{slug}.
 */
#[Fillable(['name', 'slug', 'description', 'sort_order', 'created_by', 'updated_by'])]
class BlogCategory extends Model
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    public function url(): string
    {
        return route('blog.category', $this);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
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
