<?php

namespace App\Filament\Resources\SocialLinks\Tables;

use App\Models\SocialLink;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SocialLinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('icon.path')
                    ->label('')
                    ->disk(fn (SocialLink $record): string => $record->icon?->disk ?? 'public')
                    ->height(32)
                    ->width(32)
                    ->extraImgAttributes(['class' => 'rounded object-cover']),
                TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('url')
                    ->limit(40)
                    ->searchable(),
                IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
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
