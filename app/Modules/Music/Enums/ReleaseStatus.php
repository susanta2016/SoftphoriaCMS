<?php

namespace App\Modules\Music\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Shared by Album and Single — both `albums` and `singles` carry the same
 * `status`/`publish_at` shape, so unlike Podcast (where the show and episode
 * tables genuinely differ — no publish_at on `podcasts`), one enum covers
 * both rather than two byte-identical copies. Mirrors
 * App\Modules\Podcast\Enums\PodcastEpisodeStatus's four states.
 */
enum ReleaseStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'warning',
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
