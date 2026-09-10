<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The two visibility states a Gratitude Journal light_posts row can have
 * (simplified from three states to two, 2026-09-10 — the previous
 * `Community` case was removed entirely; see
 * database/migrations/2026_09_10_090000_migrate_community_gratitude_journal_entries_to_public.php
 * for the one-way data migration that folded every legacy `community` row
 * into `public`):
 *
 * - Public: shown on the homepage carousel
 *   (HomeController::latestGratitudeEntries()) AND on the shared member
 *   feed (GratitudeJournalFeedController) — the two are no longer mutually
 *   exclusive surfaces. Can receive the 🙌 reaction on the shared feed. A
 *   registration-time Light Post is always this value.
 * - Private: visible only to its owner, in their own Account "Your
 *   Entries" list. Never shown on the homepage, the shared feed, search, or
 *   the sitemap, and never reactable.
 */
enum GratitudeJournalVisibility: string implements HasColor, HasLabel
{
    case Public = 'public';
    case Private = 'private';

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Private => 'Private',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::Private => 'gray',
        };
    }
}
