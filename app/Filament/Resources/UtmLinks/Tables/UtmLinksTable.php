<?php

namespace App\Filament\Resources\UtmLinks\Tables;

use App\Models\UtmLink;
use App\Shared\Support\Marketing\UtmParameters;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UtmLinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (UtmLink $record): string => 'Campaign: '.$record->utm_campaign),
                TextColumn::make('utm_source')
                    ->label('Channel')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => (string) UtmParameters::sourceLabel($state))
                    ->sortable(),
                TextColumn::make('generated_url')
                    ->label('Link')
                    ->state(fn (UtmLink $record): string => $record->url())
                    ->limit(60)
                    ->tooltip(fn (UtmLink $record): string => $record->url())
                    ->copyable()
                    // limit() shortens the display only; always copy the full link.
                    ->copyableState(fn (UtmLink $record): string => $record->url())
                    ->copyMessage('Link copied')
                    ->icon(Heroicon::OutlinedClipboardDocument)
                    ->iconPosition('after'),
                ToggleColumn::make('is_enabled')
                    ->label('Enabled'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('utm_source')
                    ->label('Channel')
                    ->options(fn (): array => self::sourceOptions()),
                TernaryFilter::make('is_enabled')
                    ->label('Enabled'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25);
    }

    /**
     * @return array<string, string>
     */
    private static function sourceOptions(): array
    {
        return UtmLink::query()
            ->distinct()
            ->orderBy('utm_source')
            ->pluck('utm_source')
            ->mapWithKeys(fn (string $source): array => [$source => (string) UtmParameters::sourceLabel($source)])
            ->all();
    }
}
