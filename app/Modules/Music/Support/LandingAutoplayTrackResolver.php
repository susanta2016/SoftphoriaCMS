<?php

namespace App\Modules\Music\Support;

use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Models\Track;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Support\Collection;

/**
 * Resolves the admin-picked, ordered playlist of Tracks for the Music
 * landing page's autoplay enhancement (Website Setup > Music > Landing Page
 * Autoplay, App\Modules\Music\Filament\Pages\MusicLandingAutoplaySettings),
 * stored by ID only via the generic settings table (SettingsRepository,
 * group "music", key landing_autoplay_track_ids — a JSON list of integer
 * Track ids, in play order). Shared by App\Http\Controllers\Music\
 * MusicController::index() (deciding whether to render the autoplay banner
 * at all, and what to list) and App\Http\Controllers\Music\
 * MusicLandingAutoplayStreamController (the actual unrestricted stream
 * endpoint), so the exact same fail-safe checks apply to both — a track good
 * enough to render is always good enough to stream, and vice versa; the
 * stream endpoint addresses tracks by their position in this very list, so
 * there is no way to reach it with a track this resolver would refuse to
 * render.
 *
 * Fails safe, per track: a deleted Track, one that is no longer Published
 * (or whose parent Album/Single is no longer Published), or one with no
 * uploaded audio is silently skipped — the rest of the playlist still plays,
 * and an empty result means "no autoplay", never an error.
 * config('features.music_landing_autoplay_enabled') is the master switch —
 * off entirely disables autoplay regardless of what is configured, without
 * touching the stored setting itself.
 *
 * Backwards compatible with the earlier single-track setting
 * (landing_autoplay_track_id): used as a one-item playlist until an admin
 * saves the new list for the first time.
 */
class LandingAutoplayTrackResolver
{
    /**
     * @return Collection<int, Track> playable tracks, in configured order
     */
    public function resolve(SettingsRepository $settings): Collection
    {
        if (! config('features.music_landing_autoplay_enabled')) {
            return collect();
        }

        $ids = $this->configuredIds($settings);

        if ($ids === []) {
            return collect();
        }

        $tracks = Track::query()->published()->with(['album', 'single'])->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)
            ->map(fn (int $id): ?Track => $tracks->get($id))
            ->filter(fn (?Track $track): bool => $track !== null
                && $track->audio_media_id
                && ($track->album?->status === ReleaseStatus::Published
                    || $track->single?->status === ReleaseStatus::Published))
            ->values();
    }

    /**
     * @return list<int>
     */
    public function configuredIds(SettingsRepository $settings): array
    {
        $raw = $settings->get('music', 'landing_autoplay_track_ids');

        if ($raw === null) {
            $legacy = $settings->get('music', 'landing_autoplay_track_id');

            return $legacy ? [(int) $legacy] : [];
        }

        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $decoded))));
    }
}
