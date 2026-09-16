<?php

namespace App\Filament\Resources\LightPostFlags;

use App\Actions\GratitudeJournal\DeleteGratitudeJournalEntryAction;
use App\Filament\Resources\LightPostFlags\Pages\ListLightPostFlags;
use App\Filament\Resources\LightPostFlags\Pages\ViewLightPostFlag;
use App\Filament\Resources\LightPostFlags\Schemas\LightPostFlagInfolist;
use App\Filament\Resources\LightPostFlags\Tables\LightPostFlagsTable;
use App\Models\LightPost;
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
 * The "Flagged Journal Entries" moderation queue for 🚩-reported Gratitude
 * Journal shared-feed entries — a sibling to App\Filament\Resources\
 * ReviewFlags\ReviewFlagResource ("Admin Reviews"), scoped to
 * LightPost::flagged() instead of every Review comment. A separate
 * resource rather than folded into that one: a Gratitude Journal entry is
 * a LightPost, not a Review, so it has no shared table/model to reuse.
 * Gratitude Journal otherwise has no admin moderation surface at all (no
 * status column, no approve/reject workflow) — reporting is the only lever,
 * so "resolving" a report here means either dismissing it (unfounded) or
 * deleting the entry outright (the closest equivalent to ReviewResource's
 * Reject, since a LightPost can't just be hidden by flipping a status).
 */
class LightPostFlagResource extends Resource
{
    protected static ?string $model = LightPost::class;

    protected static ?string $slug = 'flagged-journal-entries';

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Flagged Journal Entries';

    public static function shouldRegisterNavigation(): bool
    {
        return config('admin_ui.show_community_menu');
    }

    protected static ?string $modelLabel = 'Flagged Journal Entry';

    protected static ?string $pluralModelLabel = 'Flagged Journal Entries';

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
        return LightPostFlagInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LightPostFlagsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLightPostFlags::route('/'),
            'view' => ViewLightPostFlag::route('/{record}'),
        ];
    }

    /**
     * Clears every report on this entry without deleting it — for a report
     * the admin judges unfounded. The entry stays exactly as it is.
     */
    public static function dismissAction(): Action
    {
        return Action::make('dismiss')
            ->label('Dismiss Reports')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Clears all reports on this entry. The entry itself is left exactly as it is.')
            ->action(function (LightPost $record): void {
                $record->flags()->delete();

                Notification::make()->title('Reports dismissed')->success()->send();
            });
    }

    /**
     * The equivalent of ReviewResource::rejectAction() for a LightPost —
     * there's no status column to flip, so removing the entry from the
     * shared feed means deleting the row (reuses
     * App\Actions\GratitudeJournal\DeleteGratitudeJournalEntryAction, the
     * same action a member's own "delete my entry" control uses). Redirects
     * back to the list since the record no longer exists to view.
     */
    public static function deleteEntryAction(): Action
    {
        return Action::make('delete-entry')
            ->label('Delete Entry')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Permanently deletes this Gratitude Journal entry from the shared feed. This cannot be undone.')
            ->action(function (LightPost $record): void {
                app(DeleteGratitudeJournalEntryAction::class)->handle($record);

                Notification::make()->title('Entry deleted')->success()->send();
            })
            ->successRedirectUrl(fn (): string => static::getUrl('index'));
    }
}
