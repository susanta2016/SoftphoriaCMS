<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProseCollections\RelationManagers;

use App\Modules\PoetryProse\Filament\Resources\PoetryProses\PoetryProseResource;
use App\Modules\PoetryProse\Models\PoetryProse;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * A read-only membership view over the one-collection-per-entry HasMany
 * (poetry_prose.collection_id, client-confirmed, final) — deliberately has
 * no form/AttachAction: which collection an entry belongs to is set from
 * the entry's own PoetryProseResource form (a single Select), never from a
 * second parallel UI here. Mirrors TracksRelationManager's "link out to the
 * real resource" shape, adapted for a plain FK instead of a pivot.
 */
class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    protected static ?string $title = 'Entries';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('content_type')->badge(),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('publish_at', 'desc')
            ->headerActions([
                Action::make('addEntry')
                    ->label('Add Entry')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(fn (): string => PoetryProseResource::getUrl('create'))
                    ->tooltip('Select this collection under Classification once the create form opens.'),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon(Heroicon::OutlinedPencil)
                    ->url(fn (PoetryProse $record): string => PoetryProseResource::getUrl('edit', ['record' => $record])),
                Action::make('removeFromCollection')
                    ->label('Remove from Collection')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Removes this entry from the collection only — the entry itself is not deleted.')
                    ->action(fn (PoetryProse $record) => $record->update(['collection_id' => null])),
            ])
            ->toolbarActions([])
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25);
    }
}
