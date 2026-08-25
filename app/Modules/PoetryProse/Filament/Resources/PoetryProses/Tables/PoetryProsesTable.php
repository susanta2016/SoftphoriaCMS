<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProses\Tables;

use App\Modules\PoetryProse\Enums\PoetryProseContentType;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PoetryProsesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['featuredImage', 'author', 'collection']))
            ->columns([
                ImageColumn::make('featuredImage.path')
                    ->label('')
                    ->disk(fn ($record): string => $record->featuredImage?->disk ?? 'public')
                    ->height('auto')
                    ->extraImgAttributes(['class' => 'rounded object-cover', 'style' => 'max-width:120px;max-height:120px;']),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => $record->author?->name),
                TextColumn::make('content_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('categories.name')
                    ->label('Categories')
                    ->badge()
                    ->separator(','),
                TextColumn::make('collection.title')
                    ->label('Collection')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('publish_at')
                    ->label('Publish Date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(PoetryProseStatus::options()),
                SelectFilter::make('content_type')->label('Content Type')->options(PoetryProseContentType::options()),
                SelectFilter::make('categories')
                    ->label('Category')
                    ->relationship('categories', 'name', fn ($query) => $query->where('type', 'poetry_prose')),
                SelectFilter::make('collection_id')
                    ->label('Collection')
                    ->relationship('collection', 'title'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->toolbarActions([])
            ->defaultSort('publish_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No Poetry/Prose entries yet')
            ->emptyStateDescription('Create the first essay, reflection, hymn, poem, or article.')
            ->emptyStateIcon('heroicon-o-pencil-square');
    }
}
