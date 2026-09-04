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

    /*
    |--------------------------------------------------------------------------
    | Gratitude Journal Character Limit
    |--------------------------------------------------------------------------
    |
    | Maximum length of a Gratitude Journal entry (App\Actions\GratitudeJournal,
    | App\Http\Controllers\Account\GratitudeJournalController) — a
    | light_posts row with source = journal. Deliberately independent of
    | light_post_max_length above: the client requires this configurable
    | ONLY through this env var, with no Filament/admin setting and no
    | per-entry database column, and it must never change the registration
    | flow's own already-shipped 500-character limit.
    |
    */

    'gratitude_journal_max_length' => (int) env('GRATITUDE_JOURNAL_MAX_LENGTH', 100),

    /*
    |--------------------------------------------------------------------------
    | Gratitude Journal Retention (calendar months)
    |--------------------------------------------------------------------------
    |
    | How long a Gratitude Journal entry (light_posts, source = journal —
    | Public or Private alike) is kept before DeleteExpiredGratitudeJournalEntriesCommand
    | removes it, on a daily schedule (bootstrap/app.php). ENV-only, per the
    | client's requirement: no Filament/admin setting, no database setting,
    | no per-user setting. Registration-time Light Posts (source =
    | registration) are never touched by this — see that command's own
    | docblock and its journal()-scoped query.
    |
    */

    'gratitude_journal_retention_months' => (int) env('GRATITUDE_JOURNAL_RETENTION_MONTHS', 6),

    /*
    |--------------------------------------------------------------------------
    | Module-Level Comment / Reaction Toggles (Light Posts / Music / Podcast)
    |--------------------------------------------------------------------------
    |
    | Client-confirmed (2026-09-04): the shared App\Models\Review comment
    | architecture and App\Models\Reaction 🙌 architecture (config/reviews.php)
    | stay generic/shared code, but each module now independently controls
    | whether its own comment form and reaction button are exposed. Checked
    | by each module's own thin ReviewController/ReactionController (server-
    | side enforcement, not just a hidden UI element) and by the matching
    | Blade view. Never read via env() outside this file, per project
    | convention. Gratitude Journal is untouched by any of these — it has no
    | Review/Reaction relationship at all.
    |
    | Poetry/Prose ("Light Posts" is display text only — the underlying
    | routes/models/tables keep their existing "poetry-prose"/PoetryProse
    | naming) comments are word-counted, not character-counted, unlike every
    | other module — see poetry_prose_comment_max_words below and
    | config('reviews.max_length') for the unrelated, still-shared
    | character limit that Music/Podcast continue to use unchanged.
    |
    */

    'poetry_prose_comments_enabled' => env('POETRY_PROSE_COMMENTS_ENABLED', true),

    'poetry_prose_reactions_enabled' => env('POETRY_PROSE_REACTIONS_ENABLED', false),

    'poetry_prose_comment_max_words' => (int) env('POETRY_PROSE_COMMENT_MAX_WORDS', 50),

    'music_comments_enabled' => env('MUSIC_COMMENTS_ENABLED', false),

    'music_reactions_enabled' => env('MUSIC_REACTIONS_ENABLED', true),

    'podcast_comments_enabled' => env('PODCAST_COMMENTS_ENABLED', false),

    'podcast_reactions_enabled' => env('PODCAST_REACTIONS_ENABLED', true),

];
