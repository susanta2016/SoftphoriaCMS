<?php

namespace App\Shared\Support\Media;

/**
 * Parses a YouTube-only URL (youtu.be, youtube.com/watch, /embed, /shorts)
 * into its video ID and a playable embed URL — for any new field that must
 * accept YouTube and nothing else (Album's embed_video_url; the Music
 * listening page's own pre-existing inline parser and
 * PodcastEpisode::youtubeVideoId() are untouched, each already scoped to its
 * own model/view and not shared here, per the client's explicit "do not
 * alter Track/Podcast video behavior" instruction).
 */
class YoutubeUrl
{
    public static function videoId(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $url = trim($url);

        // Anchored start-to-end (only an optional trailing query string/
        // fragment is allowed) — unlike a bare substring search, this
        // rejects a YouTube URL merely embedded inside a larger string
        // (e.g. arbitrary <iframe> HTML), which the admin field must never
        // accept.
        if (preg_match('~^https?://(?:www\.)?youtu\.be/([\w-]+)(?:[?#].*)?$~i', $url, $m)
            || preg_match('~^https?://(?:www\.)?youtube\.com/(?:watch\?v=|embed/|shorts/)([\w-]+)(?:[?&#].*)?$~i', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function embedUrl(?string $url): ?string
    {
        $videoId = self::videoId($url);

        return $videoId ? "https://www.youtube.com/embed/{$videoId}?rel=0" : null;
    }

    public static function isValid(?string $url): bool
    {
        return self::videoId($url) !== null;
    }
}
