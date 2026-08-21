<?php

namespace App\Filament\Support\Media;

use App\Models\Media;
use Illuminate\Support\HtmlString;

/**
 * The single place that renders an admin audio/video player pointing at the
 * existing private-media streaming route (`media.stream` /
 * StreamMediaController — admin-gated, private `local` disk, never a public
 * `/storage/...` URL). Reused by MediaForm's own Preview section and by
 * MediaPicker's field preview (which is how Track's audio_media_id field
 * gets a player "for free" — see TrackForm/TracksTable, the only current
 * MediaCategory::Audio picker in the app) — one canonical implementation
 * instead of the HTML string being duplicated per consumer.
 *
 * Playback-only: this never touches Commerce (no entitlement, no download
 * count, no download log) — it is the same admin-preview mechanism
 * StreamMediaController already gates on canAccessPanel(), completely
 * separate from AuthorizeTrackDownloadAction's customer-facing download
 * path.
 */
class MediaPreview
{
    public static function audioPlayer(Media $media): HtmlString
    {
        return new HtmlString(sprintf(
            '<audio controls preload="none" style="width:100%%;max-width:480px"><source src="%s" type="%s"></audio>',
            e(route('media.stream', $media)),
            e($media->mime_type),
        ));
    }

    public static function videoPlayer(Media $media): HtmlString
    {
        return new HtmlString(sprintf(
            '<video controls preload="none" style="width:100%%;max-width:640px;border-radius:0.5rem"><source src="%s" type="%s"></video>',
            e(route('media.stream', $media)),
            e($media->mime_type),
        ));
    }

    public static function empty(string $label): HtmlString
    {
        return new HtmlString(sprintf(
            '<span style="color:#6b7280;font-size:0.875rem">%s</span>',
            e($label),
        ));
    }
}
