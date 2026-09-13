<?php

namespace App\Modules\Music\Filament\Support;

use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\PodcastEpisode;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The single, shared "You May Also Like — Podcast Episodes" field — used
 * identically by AlbumForm and SingleForm, the two Music release entities
 * that own the existing "You may also like" section on their own detail
 * page (music/listening.blade.php). Cross-content admin-curated suggestion
 * feature, never automatic/algorithmic — see App\Modules\Music\Models\
 * Album::podcastSuggestions()/Single::podcastSuggestions().
 *
 * Entirely hidden (not merely disabled) when config('features.
 * podcast_suggestions_enabled') is off — the server-side check this
 * requires; a CSS-only hide would leave the field, and thus the feature,
 * still reachable. Also hidden on Create (no $record yet — the relationship
 * needs a real parent id to attach to, same reasoning as both forms' own
 * existing Purchase Readiness section).
 */
class PodcastSuggestionsField
{
    public static function make(): Section
    {
        return Section::make('You May Also Like — Podcast Episodes')
            ->description('Optional. Hand-pick specific podcast episodes to suggest here — never an automatic recommendation. Leave empty for no suggestions.')
            ->visible(fn (?Model $record): bool => $record !== null && config('features.podcast_suggestions_enabled'))
            ->schema([
                Select::make('podcastSuggestions')
                    ->hiddenLabel()
                    ->relationship(
                        name: 'podcastSuggestions',
                        titleAttribute: 'title',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->where('status', PodcastEpisodeStatus::Published)
                            ->whereHas('podcast', fn (Builder $q) => $q->where('status', PodcastStatus::Published))
                            ->orderBy('title'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (PodcastEpisode $episode): string => $episode->episode_number
                        ? "{$episode->title} (Episode {$episode->episode_number}, {$episode->publish_date?->format('M j, Y')})"
                        : "{$episode->title} ({$episode->publish_date?->format('M j, Y')})")
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->saveRelationshipsUsing(function (Model $record, array $state): void {
                        $record->podcastSuggestions()->sync(
                            collect($state)->values()->mapWithKeys(fn ($id, $index): array => [$id => ['sort_order' => $index]])->all()
                        );
                    }),
            ]);
    }
}
