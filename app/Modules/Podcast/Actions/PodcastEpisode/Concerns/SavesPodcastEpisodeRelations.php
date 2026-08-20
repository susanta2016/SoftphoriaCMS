<?php

namespace App\Modules\Podcast\Actions\PodcastEpisode\Concerns;

use App\Modules\Podcast\Models\PodcastEpisode;

/**
 * Shared by CreatePodcastEpisodeAction/UpdatePodcastEpisodeAction. Streaming
 * links (podcast_links) and seo_metadata are both separate tables, not
 * podcast_episodes columns, so plain fill()/save() never touches them —
 * same reasoning as Pages' SavesPageSeo/SyncsPageSections.
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
     * Delete-and-recreate rather than diffing — streaming links are a
     * small, admin-curated list (not user-generated data with IDs worth
     * preserving across saves), so this is simpler than tracking which rows
     * changed and is exactly what the Repeater's plain array state gives us.
     *
     * @param  array<int, array<string, mixed>>  $links
     */
    protected function syncLinks(PodcastEpisode $episode, array $links): void
    {
        $episode->links()->delete();

        foreach (array_values($links) as $index => $link) {
            if (blank($link['url'] ?? null)) {
                continue;
            }

            $episode->links()->create([
                'provider' => $link['provider'],
                'url' => $link['url'],
                'sort_order' => $index,
            ]);
        }
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
