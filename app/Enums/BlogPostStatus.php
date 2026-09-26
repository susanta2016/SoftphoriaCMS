<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A blog post is either a Draft or Published. "Scheduled" is not a status
 * of its own: a Published post whose published_at is still in the future
 * stays off the site until that moment (BlogPost::scopeLive()), so no
 * scheduler job is needed to flip it.
 */
enum BlogPostStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
        };
    }
}
