<?php

namespace App\Modules\Music\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * music_streaming_links.provider is a plain string column (same reasoning as
 * App\Modules\Podcast\Enums\PodcastLinkProvider) — this enum only curates
 * the admin form's Select options and never becomes a DB constraint. Other
 * lets an admin label a provider this list doesn't name yet without a schema
 * change. Providers per the Master Scope Specification §8.1: Spotify, Apple
 * Music, YouTube and SoundCloud.
 */
enum MusicLinkProvider: string implements HasLabel
{
    case Spotify = 'spotify';
    case AppleMusic = 'apple_music';
    case YouTube = 'youtube';
    case SoundCloud = 'soundcloud';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Spotify => 'Spotify',
            self::AppleMusic => 'Apple Music',
            self::YouTube => 'YouTube',
            self::SoundCloud => 'SoundCloud',
            self::Other => 'Other',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $provider) => [$provider->value => $provider->getLabel()])
            ->all();
    }
}
