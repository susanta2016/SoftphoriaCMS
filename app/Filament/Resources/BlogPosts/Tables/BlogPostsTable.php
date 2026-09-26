<?php

namespace App\Filament\Resources\BlogPosts\Tables;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BlogPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['cover', 'category'])->withCount(['comments', 'reactions']))
            ->columns([
                ImageColumn::make('cover.path')
                    ->label('')
                    ->disk(fn (BlogPost $record): string => $record->cover?->disk ?? 'public')
                    ->height(40)
                    ->width(64),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(60)
                    ->description(fn (BlogPost $record): ?string => $record->category?->name),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (BlogPostStatus $state, BlogPost $record): string => $state === BlogPostStatus::Published && $record->published_at?->isFuture()
                        ? 'Scheduled'
                        : $state->getLabel())
                    ->color(fn (BlogPostStatus $state, BlogPost $record): string => $state === BlogPostStatus::Published && $record->published_at?->isFuture()
                        ? 'warning'
                        : $state->getColor()),
                TextColumn::make('published_at')
                    ->label('Publish date')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->placeholder('—'),
                ToggleColumn::make('is_featured')
                    ->label('Featured'),
                TextColumn::make('comments_count')
                    ->label('Comments')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('reactions_count')
                    ->label('Reactions')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(BlogPostStatus::class),
                SelectFilter::make('blog_category_id')->label('Category')->relationship('category', 'name'),
                SelectFilter::make('tags')->relationship('tags', 'name')->multiple(),
                TernaryFilter::make('is_featured')->label('Featured'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (BlogPost $record): string => $record->url(), shouldOpenInNewTab: true)
                    ->visible(fn (BlogPost $record): bool => $record->isLive()),
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->defaultSort('published_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25);
    }
}
