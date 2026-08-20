<?php

namespace App\Modules\Music\Models;

use App\Modules\Music\Enums\MusicLinkProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One streaming/provider link for a release (Database Specification §19's
 * `music_streaming_links` table) — e.g. "Listen on Spotify". Belongs to
 * either an Album or a Single (exactly one of album_id/single_id is set),
 * same shape as music_categories/music_tags.
 */
#[Fillable(['album_id', 'single_id', 'provider', 'url', 'sort_order'])]
class MusicStreamingLink extends Model
{
    protected function casts(): array
    {
        return [
            'provider' => MusicLinkProvider::class,
        ];
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function single(): BelongsTo
    {
        return $this->belongsTo(Single::class);
    }
}
