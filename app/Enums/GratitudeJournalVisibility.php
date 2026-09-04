<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The three visibility states a light_posts row can have (Gratitude
 * Journal three-state visibility change, 2026-09-05), replacing the
 * previous is_public boolean:
 *
 * - Public: unchanged existing behavior — shown on the homepage carousel
 *   (HomeController::latestGratitudeEntries()); a registration-time Light
 *   Post is always this value.
 * - Private: new — visible only to its owner, in their own Account
 *   "Your Entries" list. Never shown anywhere else.
 * - Community: the exact existing behavior the old is_public = false
 *   ("Private") state already had — shown on the shared member feed
 *   (GratitudeJournalFeedController), never the homepage. Only a Gratitude
 *   Journal entry (source = journal) can ever be this value — the
 *   registration flow (CreatesLightPostOnRegistration) never sets it.
 */
enum GratitudeJournalVisibility: string implements HasColor, HasLabel
{
    case Public = 'public';
    case Private = 'private';
    case Community = 'community';

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Private => 'Private',
            self::Community => 'For Community',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::Private => 'gray',
            self::Community => 'info',
        };
    }
}
