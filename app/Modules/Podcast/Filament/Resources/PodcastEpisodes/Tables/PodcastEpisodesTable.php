<?php

namespace App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Tables;

use App\Filament\Support\Media\MediaPreview;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class PodcastEpisodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['audio', 'video']))
            ->columns([
                ImageColumn::make('artwork.path')
                    ->label('')
                    ->disk(fn ($record): string => $record->artwork?->disk ?? 'public')
                    // A true max-width/max-height cap (not a fixed square
                    // crop), same convention as the Media list — see
                    // MediaTable::configure(). ->width() is deliberately
                    // left unset since it would add its own competing
                    // fixed-width style.
                    ->height('auto')
                    ->extraImgAttributes(['class' => 'rounded object-cover', 'style' => 'max-width:180px;max-height:180px;']),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => $record->podcast?->title),
                TextColumn::make('audio_preview')
                    ->label('Audio')
                    ->html()
                    // Admin verification/playback only, via the existing
                    // private-media streaming route — see MediaPreview's
                    // docblock. embed_url stays a separate, external-only
                    // streaming reference, never treated as this source.
                    ->getStateUsing(fn (PodcastEpisode $record): HtmlString => $record->audio
                        ? MediaPreview::audioPlayer($record->audio)
                        : MediaPreview::empty('No audio')),
                TextColumn::make('video_preview')
                    ->label('Video')
                    ->html()
                    // Same admin-only mechanism/guarantees as the Audio
                    // column above — distinct from embed_url, which is
                    // always an external reference, never this source.
                    ->getStateUsing(fn (PodcastEpisode $record): HtmlString => $record->video
                        ? MediaPreview::videoPlayer($record->video)
                        : MediaPreview::empty('No video'))
                    // Temporary presentation-mode hide — UI only, see config/admin_ui.php.
                    ->visible(fn (): bool => config('admin_ui.show_video_fields')),
                TextColumn::make('season_episode')
                    ->label('Season / Episode')
                    ->state(fn ($record): string => collect([
                        $record->season ? "S{$record->season}" : null,
                        $record->episode_number ? "E{$record->episode_number}" : null,
                    ])->filter()->join(' ') ?: '—'),
                TextColumn::make('categories.name')
                    ->label('Topics')
                    ->badge()
                    ->separator(','),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('publish_date')
                    ->label('Publish Date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(PodcastEpisodeStatus::options()),
                SelectFilter::make('podcast_id')
                    ->label('Podcast')
                    ->options(fn (): array => Podcast::query()->orderBy('title')->pluck('title', 'id')->all()),
                SelectFilter::make('categories')
                    ->label('Topic')
                    ->relationship('categories', 'name', fn ($query) => $query->where('type', 'podcast')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->toolbarActions([])
            ->defaultSort('publish_date', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25);
    }
}
