<?php

namespace App\Modules\Podcast\Models;

use App\Modules\Podcast\Enums\PodcastLinkProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One streaming/provider link for an episode (Database Specification §5's
 * `podcast_links` table) — e.g. "Listen on Spotify".
 */
#[Fillable(['podcast_episode_id', 'provider', 'url', 'sort_order'])]
class PodcastLink extends Model
{
    protected function casts(): array
    {
        return [
            'provider' => PodcastLinkProvider::class,
        ];
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(PodcastEpisode::class, 'podcast_episode_id');
    }
}
