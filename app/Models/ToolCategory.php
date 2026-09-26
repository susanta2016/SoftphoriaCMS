<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tool category (Admin → Tools → Categories). Shown publicly as a filter
 * on /tools only while it contains a live tool.
 */
#[Fillable(['name', 'slug', 'description', 'sort_order'])]
class ToolCategory extends Model
{
    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class);
    }
}
