<?php

namespace App\Filament\Resources\ReviewFlags\Tables;

use App\Models\Review;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * List-only (mirrors ReviewsTable) — a report is only ever created by
 * FlagReviewAction from the public site, never hand-built in the admin
 * panel. The query is already scoped to Review::flagged() by
 * ReviewFlagResource::getEloquentQuery(), so every row here has at least
 * one report.
 */
class ReviewFlagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'reviewable', 'flags.user'])->withCount('flags'))
            ->columns([
                TextColumn::make('reviewableType')->label('Content Type')->badge()->state(fn (Review $record): string => $record->reviewableType()),
                TextColumn::make('reviewableLabel')->label('Reviewed Item')->state(fn (Review $record): string => $record->reviewableLabel()),
                TextColumn::make('user.name')->label('Comment By')->searchable(),
                TextColumn::make('content')->limit(60)->wrap(),
                TextColumn::make('flags_count')->label('Reports')->badge()->color('danger')->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('created_at')->label('Submitted')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('flags_count', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No reported comments')
            ->emptyStateDescription('Comments reported via the 🚩 option on the public site will appear here.')
            ->emptyStateIcon('heroicon-o-flag');
    }
}
