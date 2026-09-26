<?php

namespace App\Filament\Resources\PortfolioItems\Tables;

use App\Models\PortfolioItem;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PortfolioItemsTable
{
    public static function configure(Table $table): Table
    {
        // Inline toggles save straight to the row, so record who changed it
        // the same way the Edit page does.
        $touch = fn (PortfolioItem $record) => $record->forceFill(['updated_by' => Auth::id()])->saveQuietly();

        return $table
            ->columns([
                ImageColumn::make('cover.path')
                    ->label('')
                    ->disk(fn (PortfolioItem $record): string => $record->cover?->disk ?? 'public')
                    ->height(40)
                    ->width(64),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn (PortfolioItem $record): ?string => $record->category),
                TextColumn::make('technologies')
                    ->badge()
                    ->limitList(3)
                    ->placeholder('—')
                    ->toggleable(),
                ToggleColumn::make('is_featured')
                    ->label('Featured')
                    ->afterStateUpdated($touch),
                ToggleColumn::make('is_published')
                    ->label('Published')
                    ->afterStateUpdated($touch),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_featured')->label('Featured'),
                TernaryFilter::make('is_published')->label('Published'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->toolbarActions([])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25);
    }
}
