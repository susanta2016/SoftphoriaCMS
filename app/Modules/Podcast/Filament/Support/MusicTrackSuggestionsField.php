<?php

namespace App\Modules\Podcast\Filament\Support;

use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Track;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The "You May Also Like — Music Tracks" field on PodcastEpisodeForm — the
 * Podcast-side mirror of App\Modules\Music\Filament\Support\
 * PodcastSuggestionsField. See App\Modules\Podcast\Models\PodcastEpisode::
 * trackSuggestions() for the relationship itself.
 *
 * Only Published tracks whose parent Album/Single is also Published are
 * offered — a Track's own status doesn't guarantee its release is publicly
 * reachable (same double-check MusicController::resolveAutoplayTrack()
 * already makes for the landing-page autoplay feature).
 */
class MusicTrackSuggestionsField
{
    public static function make(): Section
    {
        return Section::make('You May Also Like — Music Tracks')
            ->description('Optional. Hand-pick specific music tracks to suggest here — never an automatic recommendation. Leave empty for no suggestions.')
            ->visible(fn (?Model $record): bool => $record !== null && config('features.music_track_suggestions_enabled'))
            ->schema([
                Select::make('trackSuggestions')
                    ->hiddenLabel()
                    ->relationship(
                        name: 'trackSuggestions',
                        titleAttribute: 'title',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->where('status', TrackStatus::Published)
                            ->where(fn (Builder $q) => $q
                                ->whereHas('album', fn (Builder $a) => $a->where('status', ReleaseStatus::Published))
                                ->orWhereHas('single', fn (Builder $s) => $s->where('status', ReleaseStatus::Published)))
                            ->orderBy('title'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Track $track): string => "{$track->title} — ".($track->album?->title ?? $track->single?->title ?? 'Unknown release'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->saveRelationshipsUsing(function (Model $record, array $state): void {
                        $record->trackSuggestions()->sync(
                            collect($state)->values()->mapWithKeys(fn ($id, $index): array => [$id => ['sort_order' => $index]])->all()
                        );
                    }),
            ]);
    }
}
