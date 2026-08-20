<?php

namespace App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Tables;

use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Models\Podcast;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PodcastEpisodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('artwork.path')
                    ->label('')
                    ->disk(fn ($record): string => $record->artwork?->disk ?? 'public')
                    ->height(40)
                    ->width(40)
                    ->extraImgAttributes(['class' => 'rounded object-cover']),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => $record->podcast?->title),
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
