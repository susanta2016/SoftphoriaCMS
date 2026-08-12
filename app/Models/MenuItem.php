<?php

namespace App\Models;

use App\Enums\MenuItemDestinationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'label', 'destination_type', 'page_id', 'route_key', 'url', 'route_name',
    'target', 'parent_id', 'sort_order', 'is_enabled',
])]
class MenuItem extends Model
{
    protected function casts(): array
    {
        return [
            'destination_type' => MenuItemDestinationType::class,
            'is_enabled' => 'boolean',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Only ever set when destination_type = Page (ADMIN-006 §H) — Page
     * itself has no relation back; Navigation is the only side aware of
     * this link.
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id');
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
