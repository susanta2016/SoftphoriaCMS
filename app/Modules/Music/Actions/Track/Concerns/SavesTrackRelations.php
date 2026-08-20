<?php

namespace App\Modules\Music\Actions\Track\Concerns;

use App\Modules\Music\Actions\Concerns\SavesMusicSeo;
use App\Modules\Music\Models\Track;
use InvalidArgumentException;

/**
 * Shared by CreateTrackAction/UpdateTrackAction. Lyrics, song story,
 * credits, categories and tags are all separate tables, not tracks columns,
 * so plain fill()/save() never touches them — same reasoning as Podcast's
 * SavesPodcastEpisodeRelations. SEO uses the shared SavesMusicSeo trait
 * (also used by Album/Single) rather than a track-local copy.
 *
 * A track belongs to exactly one of an Album or a Single — enforced here at
 * the Action layer via resolveRelease(), by Track's own model-level
 * saving() guard as a backstop (see Track::booted()), and by a MariaDB-only
 * CHECK constraint at the raw-SQL layer. The form exposes this as a single
 * grouped Select named "release" with option keys shaped
 * "album:{id}"/"single:{id}" (TrackForm::releaseOptions()) rather than two
 * separate album_id/single_id selects, since exactly one may ever be set —
 * resolveRelease() below is the only place that string is parsed back into
 * the two real FK columns.
 */
trait SavesTrackRelations
{
    use SavesMusicSeo;

    /**
     * @param  array<string, mixed>  $data
     * @return array{album_id: ?int, single_id: ?int}
     */
    protected function resolveRelease(array $data): array
    {
        $release = (string) ($data['release'] ?? '');

        if (! str_contains($release, ':')) {
            throw new InvalidArgumentException('A track must belong to either an Album or a Single.');
        }

        [$type, $id] = explode(':', $release, 2);

        return match ($type) {
            'album' => ['album_id' => (int) $id, 'single_id' => null],
            'single' => ['album_id' => null, 'single_id' => (int) $id],
            default => throw new InvalidArgumentException("Unknown release type \"{$type}\"."),
        };
    }

    /**
     * @param  array<string, mixed>  $lyrics
     */
    protected function saveLyrics(Track $track, array $lyrics): void
    {
        if (blank($lyrics['content'] ?? null)) {
            $track->lyrics()->delete();

            return;
        }

        $track->lyrics()->updateOrCreate([], [
            'content' => $lyrics['content'],
            'visibility' => $lyrics['visibility'] ?? 'public',
        ]);
    }

    /**
     * @param  array<string, mixed>  $songStory
     */
    protected function saveSongStory(Track $track, array $songStory): void
    {
        if (blank($songStory['content'] ?? null)) {
            $track->songStory()->delete();

            return;
        }

        $track->songStory()->updateOrCreate([], [
            'content' => $songStory['content'],
            'media_id' => $songStory['media_id'] ?? null,
        ]);
    }

    /**
     * Delete-and-recreate — credits are a small, admin-curated ordered
     * list, not user-generated data with IDs worth preserving across saves.
     *
     * @param  array<int, array<string, mixed>>  $credits
     */
    protected function syncCredits(Track $track, array $credits): void
    {
        $track->credits()->delete();

        foreach (array_values($credits) as $index => $credit) {
            if (blank($credit['role'] ?? null) || blank($credit['name'] ?? null)) {
                continue;
            }

            $track->credits()->create([
                'role' => $credit['role'],
                'name' => $credit['name'],
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    protected function syncCategories(Track $track, array $categoryIds): void
    {
        $track->categories()->sync($categoryIds);
    }

    /**
     * @param  array<int, int>  $tagIds
     */
    protected function syncTags(Track $track, array $tagIds): void
    {
        $track->tags()->sync($tagIds);
    }
}
