<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One FAQ on a tool page. Only visible ones are rendered — and only those
 * go into the page's FAQPage structured data.
 */
#[Fillable(['question', 'answer', 'is_visible', 'sort_order'])]
class ToolFaq extends Model
{
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
        ];
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }
}
