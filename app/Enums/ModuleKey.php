<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The specialized content modules a navigation item may point at without
 * being a CMS Page (ADMIN-006 Navigation). Closed to exactly the 5 modules
 * confirmed across the Database Specification (§4-§8: Music, Podcast,
 * Poetry/Prose, Inspirational Resources, Community), the Implementation
 * Guide (JACOB-003..007), and ARCHITECTURE.md's reserved module namespace.
 *
 * "Writing"/"Thinking" appear in an earlier, superseded draft of the Jacob
 * Solution Specification (the same draft that also lists "Membership",
 * itself excluded from Phase 1) — they are not additional modules, they
 * are PoetryProse and InspirationalResources under earlier terminology.
 * Do not add cases for them or for Home (the site root, modeled as a plain
 * `url` destination — see MenuItemDestinationType) or for About/Contact
 * (CMS Pages, not modules).
 */
enum ModuleKey: string implements HasLabel
{
    case Music = 'music';
    case Podcast = 'podcast';
    case PoetryProse = 'poetry_prose';
    case InspirationalResources = 'inspirational_resources';
    case Community = 'community';

    public function getLabel(): string
    {
        return match ($this) {
            self::Music => 'Music',
            self::Podcast => 'Podcast',
            self::PoetryProse => 'Poetry/Prose',
            self::InspirationalResources => 'Inspirational Resources',
            self::Community => 'Community',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $key) => [$key->value => $key->getLabel()])
            ->all();
    }
}
