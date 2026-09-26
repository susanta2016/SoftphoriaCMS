<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The closed set of emoji reactions a member can leave on a blog post —
 * stored by key, never as a free-form emoji string.
 */
enum BlogReaction: string implements HasLabel
{
    case Like = 'like';
    case Love = 'love';
    case Fire = 'fire';
    case Clap = 'clap';
    case Insightful = 'insightful';

    public function emoji(): string
    {
        return match ($this) {
            self::Like => '👍',
            self::Love => '❤️',
            self::Fire => '🔥',
            self::Clap => '👏',
            self::Insightful => '💡',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Like => 'Like',
            self::Love => 'Love',
            self::Fire => 'Fire',
            self::Clap => 'Applause',
            self::Insightful => 'Insightful',
        };
    }
}
