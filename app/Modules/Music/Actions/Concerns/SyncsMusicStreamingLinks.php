<?php

namespace App\Modules\Music\Actions\Concerns;

use App\Modules\Music\Enums\MusicLinkProvider;
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
     * Delete-and-recreate rather than diffing by ID — streaming links are a
     * small, admin-curated list (not user-generated data with IDs worth
     * preserving across saves), same reasoning as Podcast's
     * SavesPodcastEpisodeRelations::syncLinks(). provider IS still
     * preserved across that delete-and-recreate, though, keyed by URL: the
     * admin form no longer collects/submits it at all (see this trait's own
     * history and EditAlbum/EditSingle's mutateFormDataBeforeFill), so
     * without this lookup every single save — even one that never touches
     * the Streaming Links section — would silently overwrite legacy
     * provider values (e.g. 'youtube') with the new-row default. A
     * genuinely new URL (never seen before on this release) still gets the
     * default.
     *
     * @param  array<int, array<string, mixed>>  $links
     */
    protected function syncStreamingLinks(Album|Single $release, array $links): void
    {
        $existingProviderByUrl = $release->streamingLinks()->pluck('provider', 'url');

        $release->streamingLinks()->delete();

        foreach (array_values($links) as $index => $link) {
            if (blank($link['url'] ?? null)) {
                continue;
            }

            $release->streamingLinks()->create([
                // pluck() above casts through the model, so an existing
                // match is already a MusicLinkProvider instance — ->value
                // on both branches keeps this a plain string either way.
                'provider' => ($existingProviderByUrl[$link['url']] ?? MusicLinkProvider::Other)->value,
                'url' => $link['url'],
                'sort_order' => $index,
            ]);
        }
    }
}
