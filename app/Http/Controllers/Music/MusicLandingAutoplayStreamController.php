<?php

namespace App\Http\Controllers\Music;

use App\Http\Controllers\Controller;
use App\Modules\Music\Support\LandingAutoplayTrackResolver;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The Music landing page's autoplay track (Website Setup > Music > Landing
 * Page Autoplay) — client-confirmed 2026-09-16: unlike every other
 * playback route in the app, this one track is served in full to every
 * visitor, guest or registered, with none of TrackStreamController's guest
 * duration truncation or registered daily-listen-quota check. Every other
 * Track on the site (including this same one, played from its own Album/
 * Single page) keeps those restrictions completely unchanged — this
 * endpoint is reachable only from the landing page's autoplay banner.
 *
 * Deliberately not TrackStreamController with a bypass flag: a spoofable
 * query param there would let anyone request an unrestricted copy of any
 * track by claiming to be "the autoplay track". This endpoint instead
 * takes no track identifier from the request at all — it derives the
 * track itself, server-side, from LandingAutoplayTrackResolver, the same
 * admin-configured setting and fail-safe checks MusicController::index()
 * uses to decide whether to render the autoplay banner in the first place.
 *
 * No completion beacon exists for this playback (see MusicController::
 * autoplayTrackPlayback()) — an ambient landing-page loop must never
 * consume a registered visitor's daily whole-song quota for other tracks.
 */
class MusicLandingAutoplayStreamController extends Controller
{
    public function __invoke(SettingsRepository $settings, LandingAutoplayTrackResolver $resolver): BinaryFileResponse
    {
        $track = $resolver->resolve($settings);

        abort_if($track === null, 404);

        $media = $track->audio;

        abort_if($media === null, 404);

        $path = Storage::disk($media->disk)->path($media->path);

        return response()->file($path, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->original_filename).'"',
        ]);
    }
}
