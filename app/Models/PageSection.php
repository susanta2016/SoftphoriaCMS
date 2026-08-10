<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['section_type', 'title', 'sort_order', 'is_enabled', 'settings_json', 'content_json'])]
class PageSection extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'settings_json' => 'array',
            'content_json' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function faqItems(): HasMany
    {
        return $this->hasMany(PageFaqItem::class, 'section_id');
    }
}
