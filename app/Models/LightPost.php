<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member-authored short public message — first captured by the
 * registration page's "Leave a Little Light ✨" prompt (RegisterFreeUserAction/
 * RegisterProUserAction, via CreatesLightPostOnRegistration). A private
 * (non-public) Light Post and a per-post detail page are both reserved for
 * a later task — nothing here builds either yet.
 */
#[Fillable(['user_id', 'content', 'is_public'])]
class LightPost extends Model
{
    use HasPublicId;

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

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }
}
