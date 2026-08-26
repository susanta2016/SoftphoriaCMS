<?php

namespace App\Http\Controllers\Music;

use App\Http\Controllers\Controller;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Track;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Public, unauthenticated, playback-only streaming for a Track's uploaded
 * audio file — the native-playback rule confirmed 2026-08-24 and built
 * 2026-08-26 alongside the public Music listening pages. Deliberately
 * structurally separate from AuthorizeTrackDownloadAction/ResolveTrackAccessAction:
 * no entitlement check, no download-count consumption, no Commerce
 * involvement at all. This never authorizes a download — it only lets the
 * browser's own <audio> element play the file inline, the same way an
 * embedded Spotify/YouTube player would, and Content-Disposition is always
 * "inline" (never "attachment") to keep that distinction real, not just
 * cosmetic.
 *
 * Gated on:
 *  - the site-wide "native playback" setting (Website Setup > Global
 *    Pricing > Music), so this can be switched off without code changes;
 *  - the track actually having an uploaded audio file;
 *  - the track and its parent Album/Single both being Published right now.
 *
 * Falls back silently to 404 otherwise — the frontend is responsible for
 * only rendering a native player when MusicController's view model says a
 * track has playable audio, and otherwise falling back to the track's
 * external MusicStreamingLink entries.
 *
 * response()->file() (not Storage::response()) is used so Symfony's
 * BinaryFileResponse::prepare() handles HTTP Range requests, required for
 * seeking/scrubbing in an <audio> player — same reasoning as
 * StreamMediaController/PublicHeroVideoStreamController.
 */
class TrackStreamController extends Controller
{
    public function __invoke(Track $track, SettingsRepository $settings): BinaryFileResponse
    {
        abort_unless($settings->get('music', 'native_playback_enabled', true), 404);
        abort_unless($track->status === TrackStatus::Published, 404);
        abort_unless($track->audio_media_id !== null, 404);

        $release = $track->album ?: $track->single;
        abort_unless($release && $release->status === ReleaseStatus::Published, 404);

        $media = $track->audio;
        abort_unless($media !== null, 404);

        return response()->file(Storage::disk($media->disk)->path($media->path), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->original_filename).'"',
        ]);
    }
}
