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
     * The form presents exactly one streaming link (state path `links.0`,
     * see PodcastEpisodeForm's docblock), so this only ever creates,
     * updates, or deletes the episode's first link (lowest sort_order) —
     * it deliberately never touches any additional podcast_links row a
     * legacy episode may still have from before the client's single-link
     * rule (2026-08-24). Those extra rows are simply invisible to/
     * unreachable from this admin form; they are not deleted merely
     * because the episode is edited and saved, since that was never asked
     * for and would silently destroy real seeded/production data (episodes
     * with 2-3 links existed before this rule).
     *
     * @param  array<int, array<string, mixed>>  $links
     */
    protected function syncPrimaryLink(PodcastEpisode $episode, array $links): void
    {
        $submitted = $links[0] ?? null;
        $existingFirst = $episode->links()->first();

        if (blank($submitted['url'] ?? null)) {
            $existingFirst?->delete();

            return;
        }

        if ($existingFirst !== null) {
            $existingFirst->update([
                'provider' => $submitted['provider'],
                'url' => $submitted['url'],
            ]);

            return;
        }

        $episode->links()->create([
            'provider' => $submitted['provider'],
            'url' => $submitted['url'],
            'sort_order' => 0,
        ]);
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
