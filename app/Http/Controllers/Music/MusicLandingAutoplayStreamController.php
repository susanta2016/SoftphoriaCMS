<?php

namespace App\Http\Controllers\Music;

use App\Http\Controllers\Controller;
use App\Modules\Music\Support\LandingAutoplayTrackResolver;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The Music landing page's autoplay playlist (Website Setup > Music >
 * Landing Page Autoplay) — client-confirmed 2026-09-16: unlike every other
 * playback route in the app, these admin-picked tracks are served in full to
 * every visitor, guest or registered, with none of TrackStreamController's
 * guest duration truncation or registered daily-listen-quota check. Every
 * other Track on the site (including these same ones, played from their own
 * Album/Single page) keeps those restrictions completely unchanged — this
 * endpoint is reachable only from the landing page's autoplay banner.
 *
 * Deliberately not TrackStreamController with a bypass flag: a spoofable
 * query param there would let anyone request an unrestricted copy of any
 * track by claiming to be "an autoplay track". This endpoint instead takes
 * no track identifier from the request at all — only a `position` (0-based)
 * into the playlist LandingAutoplayTrackResolver derives server-side from
 * the admin-configured setting, the same list and fail-safe checks
 * MusicController::index() uses to render the banner. An out-of-range
 * position 404s; there is no way to name a track outside that list.
 *
 * No completion beacon exists for this playback (see MusicController::
 * autoplayPlaylist()) — an ambient landing-page loop must never consume a
 * registered visitor's daily whole-song quota for other tracks.
 */
class MusicLandingAutoplayStreamController extends Controller
{
    public function __invoke(Request $request, SettingsRepository $settings, LandingAutoplayTrackResolver $resolver): BinaryFileResponse
    {
        $position = max(0, (int) $request->query('position', 0));

        $track = $resolver->resolve($settings)->get($position);

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
