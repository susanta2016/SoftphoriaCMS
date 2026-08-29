<?php

namespace App\Modules\Podcast\Actions\PodcastEpisode\Concerns;

use App\Modules\Podcast\Models\PodcastEpisode;

/**
 * Shared by CreatePodcastEpisodeAction/UpdatePodcastEpisodeAction.
 * seo_metadata is a separate table, not a podcast_episodes column, so plain
 * fill()/save() never touches it — same reasoning as Pages' SavesPageSeo/
 * SyncsPageSections.
 *
 * The Streaming Link field (podcast_links) was removed from the admin form
 * (2026-08-29, see PodcastEpisodeForm's docblock) — this trait no longer
 * syncs it. Any podcast_links row an episode already has is left alone.
 */
trait SavesPodcastEpisodeRelations
{
    /**
     * @param  array<string, mixed>  $seo
     */
    protected function saveSeo(PodcastEpisode $episode, array $seo): void
    {
        if (array_filter($seo, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []) === []) {
            return;
        }

        $episode->seo()->updateOrCreate([], $seo);
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    protected function syncCategories(PodcastEpisode $episode, array $categoryIds): void
    {
        $episode->categories()->sync($categoryIds);
    }

    /**
     * @param  array<int, int>  $tagIds
     */
    protected function syncTags(PodcastEpisode $episode, array $tagIds): void
    {
        $episode->tags()->sync($tagIds);
    }
}
