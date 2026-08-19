<?php

namespace App\Http\Controllers\Media;

use App\Enums\MediaCategory;
use App\Enums\PageSectionType;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\PageSection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Public, unauthenticated playback for a Hero section's "Watch Introduction"
 * video — unlike StreamMediaController (admin-only, gates on canAccessPanel),
 * this has no auth check, so it instead gates on the video actually being
 * attached to a *published* Hero section's content_json.tertiary_video_media_id
 * right now. Any other video uploaded to the Media Library (not referenced by
 * a published Hero section) stays 404 here — this is not a general public
 * media route (see ADMIN-005 scope / StreamMediaController).
 *
 * response()->file() is used (not Storage::response()) so Symfony's
 * BinaryFileResponse::prepare() handles Range requests, required for
 * seeking/scrubbing in an HTML5 <video> player.
 */
class PublicHeroVideoStreamController extends Controller
{
    public function __invoke(Media $media): BinaryFileResponse
    {
        abort_unless($media->category() === MediaCategory::Video, 404);

        $isAttachedToPublishedHero = PageSection::query()
            ->where('section_type', PageSectionType::Hero->value)
            ->where('is_enabled', true)
            ->where('content_json->tertiary_video_media_id', $media->id)
            ->whereHas('page', fn ($query) => $query->published())
            ->exists();

        abort_unless($isAttachedToPublishedHero, 404);

        return response()->file(Storage::disk($media->disk)->path($media->path), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->original_filename).'"',
        ]);
    }
}
