<?php

namespace App\Modules\Podcast\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * podcast_links.provider is a plain string column (Database Specification
 * §5 — no lookup table), so this enum only curates the admin form's Select
 * options; it never becomes a DB constraint. Other lets an admin label a
 * provider this list doesn't name yet without a schema change.
 */
enum PodcastLinkProvider: string implements HasLabel
{
    case Spotify = 'spotify';
    case ApplePodcasts = 'apple_podcasts';
    case GooglePodcasts = 'google_podcasts';
    case YouTube = 'youtube';
    case SoundCloud = 'soundcloud';
    case AmazonMusic = 'amazon_music';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Spotify => 'Spotify',
            self::ApplePodcasts => 'Apple Podcasts',
            self::GooglePodcasts => 'Google Podcasts',
            self::YouTube => 'YouTube',
            self::SoundCloud => 'SoundCloud',
            self::AmazonMusic => 'Amazon Music',
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
