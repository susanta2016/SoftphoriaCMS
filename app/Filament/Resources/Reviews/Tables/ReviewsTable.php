<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Enums\ReviewStatus;
use App\Models\Review;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * List-only (mirrors ResourceSubmissionsTable) — a Review is always created
 * by SubmitReviewAction from the public site, never hand-built in the admin
 * panel.
 */
class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'reviewable']))
            ->columns([
                TextColumn::make('reviewableLabel')->label('Reviewed Item')->state(fn (Review $record): string => $record->reviewableLabel()),
                TextColumn::make('user.name')->label('Reviewer')->searchable(),
                TextColumn::make('rating')->label('Rating')->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state)),
                TextColumn::make('content')->limit(60)->wrap(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('created_at')->label('Submitted')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ReviewStatus::options()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No reviews yet')
            ->emptyStateDescription('Reviews and ratings submitted from the public site will appear here.')
            ->emptyStateIcon('heroicon-o-star');
    }
}
