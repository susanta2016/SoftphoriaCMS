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
 *
 * **Client-confirmed (2026-09-04):** the "Community" navigation group was
 * hidden from the Filament sidebar entirely — nothing else uses this group,
 * so hiding it fully removed it from the menu. As of 2026-09-16 that's a
 * runtime toggle rather than a hardcoded `false` (shouldRegisterNavigation()
 * below) — see config/admin_ui.php's show_community_menu /
 * ADMIN_SHOW_COMMUNITY_MENU, same presentation-mode pattern the Commerce
 * module's Order/Entitlement/Subscription/DownloadLog resources already use.
 * This is a pure navigation/UI change either way: the resource, its routes,
 * the Review model, the reviews table, and all existing data are untouched,
 * and an authorized admin who navigates to /admin/reviews directly still
 * reaches it regardless of the toggle — Filament's navigation visibility is
 * independent of route registration/access control.
 *
 * A second, finer-grained toggle was added alongside the 🚩-reported-comment
 * feature: config('admin_ui.show_light_posts_comments_menu') /
 * ADMIN_SHOW_LIGHT_POSTS_COMMENTS_MENU hides only this entry, independent of
 * show_community_menu — so "Admin Reviews" (App\Filament\Resources\
 * ReviewFlags\ReviewFlagResource, the other member of the Community group)
 * can stay visible even when this one is switched off.
 */
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Light Posts & Comments';

    public static function shouldRegisterNavigation(): bool
    {
        return config('admin_ui.show_community_menu') && config('admin_ui.show_light_posts_comments_menu');
    }

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
