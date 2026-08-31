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
 * Public, unauthenticated video playback (Hero's "Watch Introduction" video,
 * and a Rich Text section's optional video — e.g. the About page's Jacob
 * d'IAWARII section) — unlike StreamMediaController (admin-only, gates on
 * canAccessPanel), this has no auth check, so it instead gates on the video
 * actually being attached to one of those content_json keys on an enabled
 * section of a *published* page right now. Any other video uploaded to the
 * Media Library (not referenced that way) stays 404 here — this is not a
 * general public media route (see ADMIN-005 scope / StreamMediaController).
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

        $isAttachedToPublishedSection = PageSection::query()
            ->where('is_enabled', true)
            ->where(fn ($query) => $query
                ->where(fn ($hero) => $hero
                    ->where('section_type', PageSectionType::Hero->value)
                    ->where('content_json->tertiary_video_media_id', $media->id))
                ->orWhere(fn ($richText) => $richText
                    ->where('section_type', PageSectionType::RichText->value)
                    ->where('content_json->video_media_id', $media->id)))
            ->whereHas('page', fn ($query) => $query->published())
            ->exists();

        abort_unless($isAttachedToPublishedSection, 404);

        return response()->file(Storage::disk($media->disk)->path($media->path), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->original_filename).'"',
        ]);
    }
}
