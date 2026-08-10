<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'meta_title', 'meta_description', 'canonical_url', 'robots',
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
}
