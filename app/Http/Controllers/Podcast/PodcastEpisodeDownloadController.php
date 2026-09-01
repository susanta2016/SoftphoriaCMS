<?php

namespace App\Http\Controllers\Podcast;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Podcast\Actions\Download\AuthorizePodcastEpisodeDownloadAction;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The authenticated, free download endpoint for a Podcast Episode's audio —
 * mirrors Music's TrackDownloadController exactly, minus any
 * Entitlement/Subscription/payment branch, since Podcast downloads need
 * none. The `auth` route middleware (see routes/web.php) is the actual
 * server-side guest denial: an unauthenticated request never reaches this
 * controller at all, it's redirected to /login — Blade hiding the Download
 * button is only a UI nicety on top of this, never the real gate.
 */
class PodcastEpisodeDownloadController extends Controller
{
    public function __invoke(Request $request, PodcastEpisode $episode, AuthorizePodcastEpisodeDownloadAction $authorize): BinaryFileResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $authorize->authorizeForUser($episode, $user, $request->ip(), $request->userAgent());

        if (! $result->authorized) {
            return back()->with('download_error', match ($result->denialReason) {
                'no_audio_asset' => 'This episode has no downloadable file yet.',
                default => 'You don\'t have download access to this episode.',
            });
        }

        $media = $result->media;

        return response()->download(
            Storage::disk($media->disk)->path($media->path),
            $media->original_filename,
        );
    }
}
