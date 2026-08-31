<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Member Subscription (Phase 1 / Phase 2 toggle)
    |--------------------------------------------------------------------------
    |
    | Client-confirmed (2026-08-31): no paid monthly membership in Phase 1.
    | When false, every subscription/Pro-Membership promotional UI surface
    | (registration's "Become a Pro Member" option, the account area's
    | Subscription nav/dashboard card, Global Pricing's Membership section,
    | the admin Subscriptions resource) is hidden. The Stripe subscription
    | code, webhook handling, database tables, and existing subscription
    | records are never touched by this flag — flipping it back to true (or
    | unsetting the env var, which defaults to false) restores the Phase 2
    | experience with no rebuild required.
    |
    */

    'member_subscription_enabled' => env('MEMBER_SUBSCRIPTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Guest Listening Limit (seconds)
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds of a Track's uploaded audio a non-registered
    | visitor may hear, per track. Enforced server-side by
    | TrackStreamController, which truncates the served audio bytes to this
    | proportion of the track's own duration_seconds — never a client-side
    | timer alone.
    |
    */

    'guest_user_listening_limit_seconds' => (int) env('GUEST_USER_LISTENING_LIMIT_SECONDS', 30),

    /*
    |--------------------------------------------------------------------------
    | Registered User Daily Whole-Song Listen Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of completed (fully played, via the <audio> element's
    | native `ended` event) track listens an authenticated user may have per
    | calendar day. Enforced server-side by TrackStreamController (checked
    | fresh on every stream request) against App\Modules\Music\Models\TrackListen,
    | the sole record of a completed listen.
    |
    */

    'registered_user_whole_song_listens_per_day' => (int) env('REGISTERED_USER_WHOLE_SONG_LISTENS_PER_DAY', 5),

    /*
    |--------------------------------------------------------------------------
    | Light Post Character Limit
    |--------------------------------------------------------------------------
    |
    | Maximum length of a "Leave a Little Light" message, including the one
    | optionally captured at registration. 500 is not a newly-confirmed
    | client number — it is the character limit already on record in the
    | Master Scope Specification for the Light Journal/Light Posts feature;
    | reused here as the current default pending explicit client
    | confirmation for this Phase-1 registration-time version specifically.
    |
    */

    'light_post_max_length' => (int) env('LIGHT_POST_MAX_LENGTH', 500),

];
