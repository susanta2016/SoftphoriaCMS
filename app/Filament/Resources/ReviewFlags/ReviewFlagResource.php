<?php

namespace App\Filament\Resources\ReviewFlags;

use App\Filament\Resources\ReviewFlags\Pages\ListReviewFlags;
use App\Filament\Resources\ReviewFlags\Pages\ViewReviewFlag;
use App\Filament\Resources\ReviewFlags\Tables\ReviewFlagsTable;
use App\Filament\Resources\Reviews\Schemas\ReviewInfolist;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The "Admin Reviews" moderation queue for 🚩-reported comments — a sibling
 * to App\Filament\Resources\Reviews\ReviewResource ("Light Posts &
 * Comments"), scoped to Review::flagged() instead of every comment. A
 * separate resource rather than a filter tab on the sibling: resolving a
 * report (dismiss vs. reject the comment) is a distinct workflow from the
 * general Approve/Reject moderation ReviewResource already does, and this
 * gives it its own dedicated Community sidebar entry, per the approved
 * request. Reuses the same Review model/table (never a duplicate) and the
 * same infolist as ReviewResource — a reported comment is still just a
 * Review, with its `flags` now the point of interest.
 */
class ReviewFlagResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $slug = 'admin-reviews';

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Admin Reviews';

    public static function shouldRegisterNavigation(): bool
    {
        return config('admin_ui.show_community_menu');
    }

    protected static ?string $modelLabel = 'Reported Comment';

    protected static ?string $pluralModelLabel = 'Reported Comments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $recordTitleAttribute = 'content';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->flagged();
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count() ?: null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReviewInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReviewFlagsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviewFlags::route('/'),
            'view' => ViewReviewFlag::route('/{record}'),
        ];
    }

    /**
     * Clears every report on this comment without hiding it — for a report
     * the admin judges unfounded. Leaves the comment's own status untouched
     * (still Approved/visible if it already was).
     */
    public static function dismissAction(): Action
    {
        return Action::make('dismiss')
            ->label('Dismiss Reports')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Clears all reports on this comment. The comment itself is left exactly as it is.')
            ->action(function (Review $record): void {
                $record->flags()->delete();

                Notification::make()->title('Reports dismissed')->success()->send();
            });
    }
}
