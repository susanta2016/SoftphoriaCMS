<?php

namespace App\Modules\Music\Support;

use App\Models\Media;
use getID3;
use Illuminate\Support\Facades\Storage;

/**
 * Reads a Track's uploaded audio file's real playtime. duration_seconds is
 * not an admin-editable field (see TrackForm's read-only "Length"
 * Placeholder) — SavesTrackRelations::detectAndSetDuration() calls this on
 * every save to keep it authoritative, since it's the only input
 * TrackStreamController has for computing a guest's truncated preview byte
 * count. An unset *or manually understated* duration there previously
 * meant a guest could receive the entire file (fixed 2026-08-31; that
 * controller also fails closed if duration is ever still unknown, rather
 * than falling back to serving the full file).
 */
class AudioDurationDetector
{
    public function detect(Media $media): ?int
    {
        $path = Storage::disk($media->disk)->path($media->path);

        if (! is_file($path)) {
            return null;
        }

        $seconds = (new getID3)->analyze($path)['playtime_seconds'] ?? null;

        if (! is_numeric($seconds) || $seconds <= 0) {
            return null;
        }

        return (int) round($seconds);
    }
}
