<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Show Video Fields (Admin) — now unused, kept for a possible Phase 2
    |--------------------------------------------------------------------------
    |
    | Formerly gated the Video MediaPicker field/"Video" preview column on
    | the Track (Music) admin screens. As of 2026-08-31, Track's Video File
    | option was removed outright (client decision, same treatment Podcast
    | Episode's Video already had — see PodcastEpisodeVideoHiddenTest) — no
    | code reads this key anymore. video_media_id, the Video
    | MediaPicker/MediaCategory, storage, and all existing Video data stay
    | fully intact in the database; only the admin form/table UI is gone.
    | Left here rather than deleted in case a future task wants it back —
    | confirm with the client before either restoring the UI or deleting
    | this key and video_media_id together.
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

    /*
    |--------------------------------------------------------------------------
    | Show Community Menu (Admin)
    |--------------------------------------------------------------------------
    |
    | Same presentation-mode switch as show_commerce_menu above, for the
    | "Community" navigation group (Light Posts & Comments —
    | App\Filament\Resources\Reviews\ReviewResource). When false, the group
    | is not rendered in the admin left sidebar — UI visibility only. The
    | resource, its routes, and all review/comment data stay fully intact;
    | flip this back to true (or unset the env var) to restore the sidebar
    | menu, no rebuild required.
    |
    */

    'show_community_menu' => env('ADMIN_SHOW_COMMUNITY_MENU', false),

    /*
    |--------------------------------------------------------------------------
    | Show Light Posts & Comments Menu (Admin)
    |--------------------------------------------------------------------------
    |
    | A finer-grained presentation-mode switch than show_community_menu above
    | — that one hides the entire "Community" sidebar group (both Light
    | Posts & Comments and Admin Reviews); this one hides only the "Light
    | Posts & Comments" entry (App\Filament\Resources\Reviews\ReviewResource)
    | while leaving "Admin Reviews" (App\Filament\Resources\ReviewFlags\
    | ReviewFlagResource) visible whenever the Community group itself is
    | shown. When false, that one entry is not rendered in the admin left
    | sidebar — UI visibility only. The resource, its routes, and all
    | review/comment data stay fully intact; flip this back to true (or
    | unset the env var) to restore it, no rebuild required.
    |
    */

    'show_light_posts_comments_menu' => env('ADMIN_SHOW_LIGHT_POSTS_COMMENTS_MENU', true),

];
