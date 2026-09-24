<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProseSubmissions;

use App\Models\User;
use App\Modules\PoetryProse\Actions\ChangePoetryProseSubmissionStatusAction;
use App\Modules\PoetryProse\Enums\PoetryProseSubmissionStatus;
use App\Modules\PoetryProse\Filament\Resources\PoetryProseSubmissions\Pages\ListPoetryProseSubmissions;
use App\Modules\PoetryProse\Filament\Resources\PoetryProseSubmissions\Pages\ViewPoetryProseSubmission;
use App\Modules\PoetryProse\Filament\Resources\PoetryProseSubmissions\Schemas\PoetryProseSubmissionInfolist;
use App\Modules\PoetryProse\Filament\Resources\PoetryProseSubmissions\Tables\PoetryProseSubmissionsTable;
use App\Modules\PoetryProse\Models\PoetryProseSubmission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Poetry/Prose → Submissions: the inbox for the public Poetry/Prose "Submit
 * Your Writing" form (2026-09-24). List + View only (no create/edit) —
 * records come exclusively from CreatePoetryProseSubmissionAction.
 * Submitted → In Review → Approved → Archived is review bookkeeping only;
 * nothing here publishes a Poetry/Prose entry.
 */
class PoetryProseSubmissionResource extends Resource
{
    protected static ?string $model = PoetryProseSubmission::class;

    protected static string|UnitEnum|null $navigationGroup = 'Poetry/Prose';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Submissions';

    protected static ?string $modelLabel = 'writing submission';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return PoetryProseSubmissionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PoetryProseSubmissionsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Unread-inbox count, so new submissions are noticeable in the nav.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = PoetryProseSubmission::query()->where('status', PoetryProseSubmissionStatus::Submitted)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPoetryProseSubmissions::route('/'),
            'view' => ViewPoetryProseSubmission::route('/{record}'),
        ];
    }

    public static function markInReviewAction(): Action
    {
        return self::statusAction('markInReview', 'Mark In Review', Heroicon::OutlinedMagnifyingGlass, 'warning', PoetryProseSubmissionStatus::InReview, 'Marked in review')
            ->visible(fn (PoetryProseSubmission $record): bool => $record->status === PoetryProseSubmissionStatus::Submitted);
    }

    public static function approveAction(): Action
    {
        return self::statusAction('approve', 'Approve', Heroicon::OutlinedCheckCircle, 'success', PoetryProseSubmissionStatus::Approved, 'Submission approved')
            ->visible(fn (PoetryProseSubmission $record): bool => in_array($record->status, [PoetryProseSubmissionStatus::Submitted, PoetryProseSubmissionStatus::InReview], true))
            ->requiresConfirmation();
    }

    public static function archiveAction(): Action
    {
        return self::statusAction('archive', 'Archive', Heroicon::OutlinedArchiveBoxArrowDown, 'danger', PoetryProseSubmissionStatus::Archived, 'Submission archived')
            ->visible(fn (PoetryProseSubmission $record): bool => $record->status !== PoetryProseSubmissionStatus::Archived)
            ->requiresConfirmation();
    }

    private static function statusAction(string $name, string $label, Heroicon $icon, string $color, PoetryProseSubmissionStatus $status, string $notice): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->action(function (PoetryProseSubmission $record) use ($status, $notice): void {
                app(ChangePoetryProseSubmissionStatusAction::class)->handle($record, $status, self::actor());

                Notification::make()->title($notice)->success()->send();
            });
    }

    public static function actor(): User
    {
        /** @var User $actor */
        $actor = Auth::user();

        return $actor;
    }
}
