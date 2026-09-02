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
 * panel. Admin-facing label is "Light Posts & Comments" (see
 * ReviewResource's own docblock) — the underlying class/table name is
 * unchanged.
 *
 * **Client-confirmed reversal (2026-09-02):** the `rating` column is
 * deliberately absent from this list — star ratings are no longer part of
 * the active public feature (see App\Actions\Review\SubmitReviewAction's
 * own docblock), so this normal moderation view shows only what a comment
 * actually is now: who submitted it, on what, and its text/status. The
 * handful of legacy rated rows can still be inspected on their own detail
 * page — see ReviewInfolist.
 */
class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'reviewable']))
            ->columns([
                TextColumn::make('reviewableType')->label('Content Type')->badge()->state(fn (Review $record): string => $record->reviewableType()),
                TextColumn::make('reviewableLabel')->label('Reviewed Item')->state(fn (Review $record): string => $record->reviewableLabel()),
                TextColumn::make('user.name')->label('Submitted By')->searchable(),
                TextColumn::make('content')->limit(60)->wrap(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('created_at')->label('Submitted')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ReviewStatus::options()),
                // Options are derived from whatever reviewable_type values
                // actually exist (Podcast today, Music, Poetry/Prose) rather
                // than a hardcoded module list, so this filter never needs
                // updating when a new module adopts the shared Review model.
                SelectFilter::make('reviewable_type')
                    ->label('Content Type')
                    ->options(fn (): array => Review::query()
                        ->distinct()
                        ->pluck('reviewable_type')
                        ->mapWithKeys(fn (string $type): array => [$type => str(class_basename($type))->headline()->toString()])
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No Light Posts or Comments yet')
            ->emptyStateDescription('Comments submitted from the public site will appear here.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right');
    }
}
