<?php

namespace App\Modules\Podcast\Support;

/**
 * The one shared "N min" label for a Podcast episode's duration_seconds,
 * used by every public episode card/row/header (Podcast landing, All
 * Episodes, episode page, Album/Single podcast suggestions, Inclusive page)
 * instead of each view repeating its own inline closure.
 *
 * A duration under one minute reads "Less than 1 min" rather than the old
 * truncated "0 min"; no duration (null/0) is no label at all.
 */
class EpisodeDuration
{
    public static function label(?int $seconds): ?string
    {
        if ($seconds === null || $seconds <= 0) {
            return null;
        }

        if ($seconds < 60) {
            return 'Less than 1 min';
        }

        return intdiv($seconds, 60).' min';
    }
}
