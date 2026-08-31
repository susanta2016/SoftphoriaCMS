<?php

namespace App\Http\Controllers\Music;

use App\Http\Controllers\Controller;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Track;
use App\Modules\Music\Support\DailyListenQuota;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The public native-playback endpoint for a Track's uploaded audio file —
 * the only audio source the frontend player uses now (see
 * resources/views/music/listening.blade.php; external MusicStreamingLink
 * URLs are never played through this or any other route). Structurally
 * separate from AuthorizeTrackDownloadAction/TrackDownloadController: no
 * entitlement, no download-count consumption, the file never leaves this
 * route as an attachment.
 *
 * Guests never receive the full file — the response is hard-truncated to
 * features.guest_user_listening_limit_seconds' worth of bytes, computed from
 * the track's own stored duration_seconds. This is a genuine server-side
 * limit: the bytes beyond that point are never read off disk or sent, so it
 * cannot be bypassed by seeking, reloading, reading the response directly,
 * or disabling client-side JavaScript. Registered users under their daily
 * completed-listen quota (features.registered_user_whole_song_listens_per_day,
 * checked fresh against TrackListen on every request) get the full,
 * Range-capable file for proper scrubbing; once the quota is reached for the
 * day, the endpoint denies the request outright rather than truncating it.
 *
 * Guest truncation fails closed: if duration_seconds is unknown (null/0),
 * the byte cap cannot be safely computed, so the guest gets a 404 rather
 * than the full, untruncated file — the bug found 2026-08-31, where a
 * track with no admin-entered duration silently bypassed the guest limit
 * entirely. SavesTrackRelations::fillDetectedDurationIfMissing() now
 * auto-fills duration_seconds from the real file at save time so this case
 * should be rare going forward, but this endpoint never trusts that alone.
 */
class TrackStreamController extends Controller
{
    public function __invoke(Request $request, Track $track): Response|BinaryFileResponse
    {
        abort_unless($track->status === TrackStatus::Published, 404);

        $media = $track->audio;
        abort_unless($media !== null, 404);

        $path = Storage::disk($media->disk)->path($media->path);

        $user = $request->user();

        if ($user !== null) {
            $quota = app(DailyListenQuota::class)->check($user->id);

            abort_if($quota['reached'], 403, 'Daily listening limit reached.');

            return response()->file($path, [
                'Content-Type' => $media->mime_type,
                'Content-Disposition' => 'inline; filename="'.addslashes($media->original_filename).'"',
            ]);
        }

        // Fail closed, never open: with no reliable duration, there is no
        // safe way to compute a byte cap, so a guest gets nothing rather
        // than an accidental full, untruncated file.
        abort_if($track->duration_seconds === null || $track->duration_seconds <= 0, 404);

        return $this->truncatedGuestPreview($path, $media->mime_type, $track->duration_seconds);
    }

    /**
     * Always serves at most the guest preview's allowed byte count, computed
     * as the same proportion of the file's total size that the configured
     * second limit is of the track's total duration — never Range-aware, so
     * a guest's request can never negotiate more bytes than this. Callers
     * must only reach this with a known, positive $durationSeconds — see
     * the fail-closed guard in __invoke() above.
     */
    private function truncatedGuestPreview(string $path, string $mimeType, int $durationSeconds): Response
    {
        $fileSize = filesize($path);
        $limitSeconds = (int) config('features.guest_user_listening_limit_seconds');

        $allowedBytes = min($fileSize, (int) floor($fileSize * ($limitSeconds / $durationSeconds)));

        $handle = fopen($path, 'rb');
        $content = $handle !== false ? (string) fread($handle, max($allowedBytes, 0)) : '';
        if ($handle !== false) {
            fclose($handle);
        }

        return response($content, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) strlen($content),
            'Accept-Ranges' => 'none',
            'Cache-Control' => 'no-store',
        ]);
    }
}
