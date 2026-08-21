<?php

namespace App\Modules\Music\Filament\Resources\Tracks\Tables;

use App\Filament\Support\Media\MediaPreview;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class TracksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['audio', 'video']))
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => $record->album?->title ?? $record->single?->title),
                TextColumn::make('audio_preview')
                    ->label('Audio')
                    ->html()
                    // Admin verification/playback only, via the existing
                    // private-media streaming route — see MediaPreview's
                    // docblock. Never touches Commerce (no entitlement, no
                    // download count) and never a public /storage/... URL.
                    ->getStateUsing(fn (Track $record): HtmlString => $record->audio
                        ? MediaPreview::audioPlayer($record->audio)
                        : MediaPreview::empty('No audio')),
                TextColumn::make('video_preview')
                    ->label('Video')
                    ->html()
                    // Same admin-only mechanism/guarantees as the Audio
                    // column above — distinct from video_embed_url, which
                    // is always an external reference, never this source.
                    ->getStateUsing(fn (Track $record): HtmlString => $record->video
                        ? MediaPreview::videoPlayer($record->video)
                        : MediaPreview::empty('No video'))
                    // Temporary presentation-mode hide — UI only, see config/admin_ui.php.
                    ->visible(fn (): bool => config('admin_ui.show_video_fields')),
                TextColumn::make('release')
                    ->label('Release')
                    ->state(fn ($record): string => $record->album ? 'Album' : 'Single')
                    ->badge()
                    ->color(fn ($record): string => $record->album ? 'info' : 'gray'),
                TextColumn::make('track_number')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('duration_seconds')
                    ->label('Length')
                    ->state(fn ($record): string => $record->duration_seconds ? gmdate('i:s', $record->duration_seconds) : '—'),
                TextColumn::make('categories.name')
                    ->label('Genres')
                    ->badge()
                    ->separator(','),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(TrackStatus::options()),
                SelectFilter::make('album_id')
                    ->label('Album')
                    ->options(fn (): array => Album::query()->orderBy('title')->pluck('title', 'id')->all()),
                SelectFilter::make('single_id')
                    ->label('Single')
                    ->options(fn (): array => Single::query()->orderBy('title')->pluck('title', 'id')->all()),
                SelectFilter::make('categories')
                    ->label('Genre')
                    ->relationship('categories', 'name', fn ($query) => $query->where('type', 'music')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->toolbarActions([])
            ->defaultSort('title')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25);
    }
}
