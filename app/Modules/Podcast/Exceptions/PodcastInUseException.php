<?php

namespace App\Modules\Podcast\Exceptions;

use App\Modules\Podcast\Models\Podcast;
use RuntimeException;

/**
 * Thrown by DeletePodcastAction when the show still has episodes.
 * podcast_episodes.podcast_id is cascadeOnDelete() at the DB level, but that
 * only fires on a real DELETE — Podcasts are soft-deleted (never
 * forceDelete(), per ARCHITECTURE.md §13), which never touches that
 * constraint, so this is the domain-layer guard against silently orphaning
 * episodes under a deleted show (same reasoning as
 * PageInUseByNavigationException).
 */
class PodcastInUseException extends RuntimeException
{
    public static function forPodcast(Podcast $podcast, int $episodeCount): self
    {
        $episodeWord = $episodeCount === 1 ? 'episode' : 'episodes';

        return new self("\"{$podcast->title}\" still has {$episodeCount} {$episodeWord}. Delete or reassign them before deleting the show.");
    }
}
