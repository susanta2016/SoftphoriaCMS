<?php

namespace App\Shared\Support\Notifications;

/**
 * Member-facing content that emails every Active member once, the first
 * time it becomes member-visible (NewContentAlerter). Implemented by Album,
 * Single, PodcastEpisode and ResourceSubmission only — never Light Posts,
 * Gratitude Journal entries, reviews or reactions. Pair with the
 * AlertsMembersWhenPublished trait, which hooks the model's saved event.
 */
interface AnnouncesNewContent
{
    /**
     * Exactly the condition the public controller checks before serving
     * this item — the alert must never link to a 404.
     */
    public function isMemberVisible(): bool;

    /**
     * The config/email_templates.php key (recipient type `user`).
     */
    public function newContentAlertKey(): string;

    /**
     * Template tokens describing this item. `user_name` and `site_name`
     * are added per recipient by SendNewContentAlertJob/TemplatedMailer.
     *
     * @return array<string, string>
     */
    public function newContentAlertVariables(): array;
}
