<?php

namespace App\Http\Controllers\Podcast;

use App\Http\Controllers\Controller;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The public, play-only streaming endpoint for a Podcast Episode's uploaded
 * audio (audio_media_id) — backs the on-page <audio> player added to
 * podcast/show.blade.php in place of the removed "Download Audio" link.
 * Open to guests and authenticated users alike (client-confirmed,
 * 2026-09-05): mirrors the existing YouTube embed's "free and unrestricted
 * for guests too" precedent (see routes/web.php's Podcast comment) rather
 * than the auth-only download gate — only the download route stays a member
 * perk, not playback.
 *
 * Structurally separate from PodcastEpisodeDownloadController/
 * AuthorizePodcastEpisodeDownloadAction: no auth check, no DownloadLog entry,
 * inline Content-Disposition (never an attachment), and — since Podcast has
 * no listen-limit feature at all — no quota logic of any kind, unlike
 * Music's TrackStreamController which this otherwise mirrors in shape.
 */
class PodcastEpisodeStreamController extends Controller
{
    public function __invoke(PodcastEpisode $episode): BinaryFileResponse
    {
        abort_unless($episode->status === PodcastEpisodeStatus::Published, 404);
        abort_unless($episode->podcast?->status === PodcastStatus::Published, 404);

        $media = $episode->audio;
        abort_unless($media !== null, 404);

        return response()->file(Storage::disk($media->disk)->path($media->path), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->original_filename).'"',
        ]);
    }
}
