<?php

namespace App\Http\Controllers\Media;

use App\Enums\MediaCategory;
use App\Http\Controllers\Controller;
use App\Models\Media;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin-only playback for audio/video in the Media Library (ADMIN-005).
 * Public-visibility media is served directly via the `public` disk's
 * symlinked URL and never reaches this controller. This is not a public
 * streaming endpoint — see ADMIN-005 scope (no CDN, no public media routes).
 *
 * response()->file() (Symfony's BinaryFileResponse) is used rather than
 * Storage::disk()->response() because BinaryFileResponse::prepare() handles
 * HTTP Range requests natively, which is required for seeking/scrubbing in
 * an <audio>/<video> player — Storage's own response() does not support it.
 */
class StreamMediaController extends Controller
{
    public function __invoke(Media $media): BinaryFileResponse
    {
        abort_unless(
            Auth::check() && Auth::user()->canAccessPanel(Filament::getPanel('admin')),
            403,
        );

        abort_unless(
            in_array($media->category(), [MediaCategory::Audio, MediaCategory::Video], true),
            404,
        );

        return response()->file(Storage::disk($media->disk)->path($media->path), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->original_filename).'"',
        ]);
    }
}
