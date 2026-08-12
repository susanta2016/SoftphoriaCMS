<?php

namespace App\Filament\Resources\Media\Tables;

use App\Enums\MediaCategory;
use App\Models\Media;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class MediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('path')
                    ->label('')
                    ->disk(fn (?Media $record): string => $record?->disk ?? 'public')
                    ->height(48)
                    ->width(48)
                    ->extraImgAttributes(['class' => 'rounded object-cover'])
                    ->visible(fn (?Media $record): bool => $record?->category() === MediaCategory::Image),
                TextColumn::make('original_filename')
                    ->label('Filename')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('mime_type')
                    ->label('Category')
                    ->badge()
                    ->formatStateUsing(fn (?Media $record): string => $record?->category()?->getLabel() ?? 'Unknown')
                    ->color(fn (?Media $record): string => $record?->category()?->getColor() ?? 'gray'),
                TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state))
                    ->sortable(),
                TextColumn::make('visibility')
                    ->badge()
                    ->sortable(),
                TextColumn::make('uploader.name')
                    ->label('Uploaded by')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(MediaCategory::options())
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return match ($data['value']) {
                            MediaCategory::Document->value => $query->where('mime_type', 'application/pdf'),
                            default => $query->where('mime_type', 'like', "{$data['value']}/%"),
                        };
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->label('View / Edit'),
                    DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalDescription(fn (Media $record): string => "Delete {$record->original_filename}? It will no longer be available for reuse."),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->label('Actions'),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25);
    }
}
