<?php

namespace App\Modules\PoetryProse\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Client-confirmed: exactly three states, no Scheduled — unlike
 * Album/Single/PodcastEpisode's four-state ReleaseStatus/PodcastEpisodeStatus,
 * Poetry/Prose publication is never automated by a scheduled command.
 * `publish_at`/`publish_date` are display fields only.
 */
enum PoetryProseStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
            self::Archived => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->getLabel()])
            ->all();
    }
}
