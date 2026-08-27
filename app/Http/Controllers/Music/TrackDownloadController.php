<?php

namespace App\Http\Controllers\Music;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Actions\Download\AuthorizeTrackDownloadAction;
use App\Modules\Music\Models\Track;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The authenticated download endpoint AuthorizeTrackDownloadAction's docblock
 * calls "a future download controller" — resolves access (active
 * Subscription or a paid, non-revoked/non-expired Entitlement), enforces the
 * download-count limit for a purchase, writes the DownloadLog row either way,
 * then streams the file from its private disk with an attachment
 * disposition. A denial redirects back with a flash message instead of a
 * bare 403 so it renders inline on the listening page.
 *
 * Guest-token downloads (from an emailed purchase link) are a separate,
 * not-yet-built endpoint — this one only ever serves Auth::user().
 */
class TrackDownloadController extends Controller
{
    public function __invoke(Request $request, Track $track, AuthorizeTrackDownloadAction $authorize): BinaryFileResponse|RedirectResponse
    {
        $result = $authorize->authorizeForUser($track, $request->user(), $request->ip(), $request->userAgent());

        if (! $result->authorized) {
            return back()->with('download_error', match ($result->denialReason) {
                'limit_reached' => 'You\'ve reached the download limit for this track.',
                'no_audio_asset' => 'This track has no downloadable file yet.',
                default => 'You don\'t have download access to this track.',
            });
        }

        $media = $result->media;

        return response()->download(
            Storage::disk($media->disk)->path($media->path),
            $media->original_filename,
        );
    }
}
