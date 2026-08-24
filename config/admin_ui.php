<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Show Video Fields (Admin)
    |--------------------------------------------------------------------------
    |
    | Temporary presentation-mode switch, Track (Music) only. When false,
    | the Video MediaPicker field and "Video" preview column are not
    | rendered on the Track admin screens — UI visibility only.
    | video_media_id, the Video MediaPicker/MediaCategory, storage, and all
    | Video data stay fully intact; flip this back to true (or unset the
    | env var) to restore the admin UI, no rebuild required.
    |
    | Podcast Episode is NOT gated by this flag. The client permanently
    | rejected Video for Episodes (2026-08-24, a confirmed product
    | requirement, not a presentation setting) — its Video field/column are
    | removed outright from PodcastEpisodeForm/PodcastEpisodesTable and do
    | not respond to this env var at all.
    |
    */

    'show_video_fields' => env('ADMIN_SHOW_VIDEO_FIELDS', true),

    /*
    |--------------------------------------------------------------------------
    | Show Commerce Menu (Admin)
    |--------------------------------------------------------------------------
    |
    | Temporary presentation-mode switch. When false, the "Commerce"
    | navigation group (Orders, Entitlements, Subscriptions, Download
    | History) is not rendered in the admin left sidebar — UI visibility
    | only. The resources, their routes, and all Commerce data stay fully
    | intact; flip this back to true (or unset the env var) to restore the
    | sidebar menu, no rebuild required.
    |
    */

    'show_commerce_menu' => env('ADMIN_SHOW_COMMERCE_MENU', false),

];
