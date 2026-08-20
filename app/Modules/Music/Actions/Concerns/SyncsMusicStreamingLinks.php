<?php

namespace App\Modules\Music\Actions\Concerns;

use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;

/**
 * Shared by Album's and Single's Create/UpdateAction pairs — both release
 * types reuse the exact same music_streaming_links table/model (Album and
 * Single both expose an identically-shaped streamingLinks() HasMany), so
 * this is the single place that reconciles it rather than duplicating the
 * same delete-and-recreate loop in SavesAlbumRelations and
 * SavesSingleRelations.
 */
trait SyncsMusicStreamingLinks
{
    /**
     * Delete-and-recreate rather than diffing — streaming links are a
     * small, admin-curated list (not user-generated data with IDs worth
     * preserving across saves), same reasoning as Podcast's
     * SavesPodcastEpisodeRelations::syncLinks().
     *
     * @param  array<int, array<string, mixed>>  $links
     */
    protected function syncStreamingLinks(Album|Single $release, array $links): void
    {
        $release->streamingLinks()->delete();

        foreach (array_values($links) as $index => $link) {
            if (blank($link['url'] ?? null)) {
                continue;
            }

            $release->streamingLinks()->create([
                'provider' => $link['provider'],
                'url' => $link['url'],
                'sort_order' => $index,
            ]);
        }
    }
}
