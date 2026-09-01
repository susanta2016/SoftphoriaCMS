<?php

namespace App\Modules\Podcast\Actions\PodcastEpisode\Concerns;

use App\Modules\Music\Support\AudioDurationDetector;
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

    /**
     * duration_seconds is not an admin-editable form field, same reasoning
     * and same reused App\Modules\Music\Support\AudioDurationDetector as
     * Track's SavesTrackRelations::detectAndSetDuration() — always
     * recomputed from the real uploaded file on every save, never trusted
     * from a prior value. The public Podcast frontend (hero/list/filters)
     * is this column's only consumer; unlike Track's, no guest byte-cap
     * depends on it, but an unset-or-stale value would still misrender the
     * episode's duration everywhere it's shown.
     */
    protected function detectAndSetDuration(PodcastEpisode $episode): void
    {
        if ($episode->audio_media_id === null) {
            if ($episode->duration_seconds !== null) {
                $episode->duration_seconds = null;
                $episode->save();
            }

            return;
        }

        $media = $episode->audio()->first();
        $seconds = $media !== null ? app(AudioDurationDetector::class)->detect($media) : null;

        if ($seconds !== $episode->duration_seconds) {
            $episode->duration_seconds = $seconds;
            $episode->save();
        }
    }
}
