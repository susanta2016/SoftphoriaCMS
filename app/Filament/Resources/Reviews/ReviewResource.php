<?php

namespace App\Filament\Resources\Reviews;

use App\Actions\Review\PublishReviewAction;
use App\Actions\Review\RejectReviewAction;
use App\Enums\ReviewStatus;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\Pages\ViewReview;
use App\Filament\Resources\Reviews\Schemas\ReviewInfolist;
use App\Filament\Resources\Reviews\Tables\ReviewsTable;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Generic, reusable moderation queue for App\Models\Review — deliberately
 * NOT namespaced under a single module's Filament directory (unlike
 * PodcastEpisodeResource) since Podcast, Music, and Inspirational Resources
 * are all meant to share this exact resource against the same polymorphic
 * reviewable_type/reviewable_id table, never one per module. List + View
 * only (mirrors ResourceSubmissionResource's exact reasoning) — a review is
 * always submitted from the public site, never hand-authored by an admin.
 * Access control is the app's one existing convention: any user with the
 * "admin" role (User::canAccessPanel()) — there is no finer-grained
 * per-resource authorization pattern anywhere else in this app to follow.
 */
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Reviews & Ratings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $recordTitleAttribute = 'content';

    public static function infolist(Schema $schema): Schema
    {
        return ReviewInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReviewsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
            'view' => ViewReview::route('/{record}'),
        ];
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Review $record): bool => $record->status !== ReviewStatus::Approved)
            ->requiresConfirmation()
            ->modalDescription('Publishes this review publicly and emails the submitter that it\'s live.')
            ->action(function (Review $record): void {
                app(PublishReviewAction::class)->handle($record);

                Notification::make()->title('Review approved and published')->success()->send();
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Review $record): bool => $record->status !== ReviewStatus::Rejected)
            ->requiresConfirmation()
            ->action(function (Review $record): void {
                app(RejectReviewAction::class)->handle($record);

                Notification::make()->title('Review rejected')->success()->send();
            });
    }
}
