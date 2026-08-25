<?php

namespace App\Modules\PoetryProse\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Client-confirmed fixed/closed list — distinct from the freeform,
 * admin-managed Category/Tag taxonomies (poetry_prose_categories/
 * poetry_prose_tags), which sit alongside this as a separate layer rather
 * than being folded into it.
 */
enum PoetryProseContentType: string implements HasColor, HasLabel
{
    case Essay = 'essay';
    case Reflection = 'reflection';
    case Hymn = 'hymn';
    case Poetry = 'poetry';
    case Article = 'article';

    public function getLabel(): string
    {
        return match ($this) {
            self::Essay => 'Essay',
            self::Reflection => 'Reflection',
            self::Hymn => 'Hymn',
            self::Poetry => 'Poetry',
            self::Article => 'Article',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Essay => 'info',
            self::Reflection => 'warning',
            self::Hymn => 'success',
            self::Poetry => 'danger',
            self::Article => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->getLabel()])
            ->all();
    }
}
