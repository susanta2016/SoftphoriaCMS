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

    /*
    |--------------------------------------------------------------------------
    | Gratitude Journal Shared Feed Reaction Toggle
    |--------------------------------------------------------------------------
    |
    | Client-confirmed (2026-09-05): the same generic App\Models\Reaction 🙌
    | architecture used by Poetry/Prose, Music, and Podcast above, extended to
    | Gratitude Journal entries on the shared member feed
    | (/inspirational-resources/gratitude-journal). No comment toggle exists
    | for Gratitude Journal — this is reaction-only. Enforced server-side by
    | GratitudeJournalReactionController, which also restricts reactable
    | LightPost rows to source = journal AND visibility = community
    | regardless of this flag — see that controller's own docblock. Code
    | default is false; only this environment's .env explicitly enables it.
    |
    */

    'gratitude_journal_reactions_enabled' => env('GRATITUDE_JOURNAL_REACTIONS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Gratitude Journal Shared Feed Report Toggle
    |--------------------------------------------------------------------------
    |
    | The 🚩 "report this entry" action on a Gratitude Journal shared-feed
    | entry — same App\Models\LightPostFlag/App\Actions\GratitudeJournal\
    | FlagGratitudeJournalEntryAction architecture as the 🚩 report on a
    | Review comment (poetry_prose_comments_enabled's sibling feature),
    | adapted for LightPost since Gratitude Journal has no separate comment
    | row of its own — the entry itself is what gets reported. Reporting
    | emails the entry's author and every admin-role user, and surfaces the
    | entry in the admin "Flagged Journal Entries" queue (App\Filament\
    | Resources\LightPostFlags\LightPostFlagResource). Enforced server-side
    | by GratitudeJournalFlagController, which also restricts flaggable
    | LightPost rows to source = journal AND visibility = public regardless
    | of this flag. Code default is false; only this environment's .env
    | explicitly enables it.
    |
    */

    'gratitude_journal_flags_enabled' => env('GRATITUDE_JOURNAL_FLAGS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Music Landing Page Autoplay
    |--------------------------------------------------------------------------
    |
    | Master switch for the Music landing page's autoplay enhancement
    | (App\Http\Controllers\Music\MusicController::resolveAutoplayTrack()).
    | When false, the public landing page never attempts autoplay regardless
    | of what Track an admin has configured, and the admin's own "Landing
    | Page Autoplay" settings page (App\Modules\Music\Filament\Pages\
    | MusicLandingAutoplaySettings) is hidden from navigation — same
    | config-gated-admin-surface pattern member_subscription_enabled already
    | uses for the Subscriptions resource. The configured Track id itself
    | (settings table, group "music") is left untouched either way, so
    | flipping this back to true (or unsetting the env var, which defaults
    | to false) restores autoplay with no reconfiguration needed.
    |
    | Client-confirmed 2026-09-16: this one configured Track is exempt from
    | every other listening restriction on the site — no guest preview
    | cutoff, no registered daily-listen quota — served by the dedicated
    | App\Http\Controllers\Music\MusicLandingAutoplayStreamController route
    | rather than the shared music.tracks.stream one every other Track
    | (including this same Track played from its own Album/Single page)
    | still uses unchanged.
    |
    */

    'music_landing_autoplay_enabled' => env('MUSIC_LANDING_AUTOPLAY_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Cross-Content "You May Also Like" Suggestions (Music <-> Podcast)
    |--------------------------------------------------------------------------
    |
    | Two independent master switches for the admin-curated suggestion
    | feature between Music (Album/Single::podcastSuggestions()) and Podcast
    | (PodcastEpisode::trackSuggestions()) — manually selected by an admin,
    | never automatic/algorithmic. Each flag gates BOTH its own admin
    | configuration UI (Filament Section ->visible()) AND its own frontend
    | rendering; turning either off never deletes the stored
    | music_podcast_suggestions/podcast_track_suggestions pivot rows, it only
    | stops them from being read/displayed. Turning a flag back on makes the
    | previously configured relationships available again immediately, with
    | no reconfiguration needed.
    |
    */

    'podcast_suggestions_enabled' => env('PODCAST_SUGGESTIONS_ENABLED', false),

    'music_track_suggestions_enabled' => env('MUSIC_TRACK_SUGGESTIONS_ENABLED', false),

];
