<?php

namespace App\Modules\Music\Support;

/**
 * m:ss formatting for a Track's duration_seconds — the one place every
 * Music view formats a duration, instead of repeating the same sprintf in
 * each Blade file.
 */
class Duration
{
    public static function format(?int $seconds): string
    {
        if (! $seconds || $seconds < 0) {
            return '--:--';
        }

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
