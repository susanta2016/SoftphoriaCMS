<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Distinguishes the existing registration-time "Leave a Little Light" post
 * from a Gratitude Journal entry — both are stored in the same light_posts
 * table (Gratitude Journal audit §3/§13). Deliberately no third "plain"
 * value yet: there is no independent, non-registration, non-journal Light
 * Post creation flow in this codebase today.
 */
enum LightPostSource: string implements HasColor, HasLabel
{
    case Registration = 'registration';
    case Journal = 'journal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Registration => 'Registration',
            self::Journal => 'Gratitude Journal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Registration => 'gray',
            self::Journal => 'success',
        };
    }
}
