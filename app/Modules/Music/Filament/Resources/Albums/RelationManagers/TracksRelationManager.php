<?php

namespace App\Modules\Music\Filament\Resources\Albums\RelationManagers;

use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Filament\Resources\Tracks\TrackResource;
use App\Modules\Music\Models\Track;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The dedicated Album -> Tracks reorder/management experience (Master Scope
 * hardening item 4) — lets an admin see and drag-reorder an album's
 * tracklist without leaving the Album edit page, updating tracks.
 * track_number directly via Filament's built-in reorderable table.
 *
 * Deliberately has no form(): every row action links out to the real
 * TrackResource create/edit pages instead of duplicating TrackForm's large
 * schema (lyrics/story/credits/SEO/etc.) in a second, parallel form. This
 * keeps exactly one place that builds/validates a Track record — see
 * CreateTrackAction/UpdateTrackAction — matching "do not unnecessarily
 * redesign the global Tracks resource".
 */
class TracksRelationManager extends RelationManager
{
    protected static string $relationship = 'tracks';

    protected static ?string $title = 'Tracks';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('track_number')->label('#'),
                TextColumn::make('title')->searchable(),
                TextColumn::make('duration_seconds')
                    ->label('Length')
                    ->state(fn (Track $record): string => $record->duration_seconds ? gmdate('i:s', $record->duration_seconds) : '—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (TrackStatus $state): string => $state->getLabel())
                    ->color(fn (TrackStatus $state): string => $state->getColor()),
            ])
            ->defaultSort('track_number')
            ->reorderable('track_number')
            ->headerActions([
                Action::make('addTrack')
                    ->label('Add Track')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(fn (): string => TrackResource::getUrl('create'))
                    ->tooltip('Select this album under Release once the create form opens.'),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon(Heroicon::OutlinedPencil)
                    ->url(fn (Track $record): string => TrackResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->toolbarActions([])
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25);
    }
}
