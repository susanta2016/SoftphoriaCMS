<?php

namespace App\Modules\Music\Support;

use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Models\Track;
use App\Shared\Services\Settings\SettingsRepository;

/**
 * Resolves the single admin-picked Track for the Music landing page's
 * autoplay enhancement (Website Setup > Music > Landing Page Autoplay,
 * App\Modules\Music\Filament\Pages\MusicLandingAutoplaySettings), stored by
 * ID only via the generic settings table (SettingsRepository, group
 * "music"). Shared by App\Http\Controllers\Music\MusicController::index()
 * (deciding whether to render the autoplay banner at all) and
 * App\Http\Controllers\Music\MusicLandingAutoplayStreamController (the
 * actual unrestricted stream endpoint), so the exact same fail-safe checks
 * apply to both — a track good enough to render the banner is always good
 * enough to stream, and vice versa; there is no way to reach the stream
 * endpoint with a track this resolver would refuse to render.
 *
 * Fails safe: a null/never-configured setting, a deleted Track, or a Track
 * that is no longer Published (or whose parent Album/Single is no longer
 * Published) all resolve to null — never an error.
 * config('features.music_landing_autoplay_enabled') is the master switch —
 * off entirely disables autoplay regardless of what Track is configured,
 * without touching the stored setting itself.
 */
class LandingAutoplayTrackResolver
{
    public function resolve(SettingsRepository $settings): ?Track
    {
        if (! config('features.music_landing_autoplay_enabled')) {
            return null;
        }

        $trackId = $settings->get('music', 'landing_autoplay_track_id');

        if (! $trackId) {
            return null;
        }

        $track = Track::query()->published()->with(['album', 'single'])->find($trackId);

        if (! $track) {
            return null;
        }

        $parentPublished = $track->album?->status === ReleaseStatus::Published
            || $track->single?->status === ReleaseStatus::Published;

        return $parentPublished ? $track : null;
    }
}
