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
 * PodcastEpisodeResource) since Podcast, Music, and Poetry/Prose are all
 * meant to share this exact resource against the same polymorphic
 * reviewable_type/reviewable_id table, never one per module. List + View
 * only (mirrors ResourceSubmissionResource's exact reasoning) — a comment
 * is always submitted from the public site, never hand-authored by an
 * admin. Access control is the app's one existing convention: any user
 * with the "admin" role (User::canAccessPanel()) — there is no finer-
 * grained per-resource authorization pattern anywhere else in this app to
 * follow.
 *
 * **Client-confirmed reversal (2026-09-02):** the underlying class/table
 * name stays `Review`/`reviews` (explicitly NOT a large-scale rename), but
 * every admin-facing label here reads "Light Posts & Comments" — the star
 * rating this resource used to moderate no longer exists in the active
 * public feature (see App\Actions\Review\SubmitReviewAction's own
 * docblock); a submission is a plain text comment now, and can no longer
 * be updated in place — every submission is its own independently-
 * moderated Review row.
 */
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Light Posts & Comments';

    protected static ?string $modelLabel = 'Light Post / Comment';

    protected static ?string $pluralModelLabel = 'Light Posts & Comments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

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
            ->modalDescription('Publishes this comment publicly and emails the submitter that it\'s live.')
            ->action(function (Review $record): void {
                app(PublishReviewAction::class)->handle($record);

                Notification::make()->title('Comment approved and published')->success()->send();
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

                Notification::make()->title('Comment rejected')->success()->send();
            });
    }
}
