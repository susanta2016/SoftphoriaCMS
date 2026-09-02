<?php

namespace App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions;

use App\Models\User;
use App\Modules\InspirationalResources\Actions\ApproveResourceSubmissionAction;
use App\Modules\InspirationalResources\Actions\ArchiveResourceSubmissionAction;
use App\Modules\InspirationalResources\Actions\MarkResourceSubmissionInReviewAction;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Pages\ListResourceSubmissions;
use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Pages\ViewResourceSubmission;
use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Schemas\ResourceSubmissionInfolist;
use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Tables\ResourceSubmissionsTable;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
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
 * List-only + View — submissions are created exclusively by
 * CreateResourceSubmissionAction from the public form, never hand-built in
 * the admin panel (mirrors OrderResource's exact reasoning). Client-
 * confirmed final workflow: Submitted → In Review → Approved → Archived,
 * a pure review queue with no editorial conversion or relation to any other
 * module (the earlier "Create Poetry/Prose Draft" action was removed
 * 2026-09-02) and no public InspirationalResource model.
 */
class ResourceSubmissionResource extends Resource
{
    protected static ?string $model = ResourceSubmission::class;

    protected static string|UnitEnum|null $navigationGroup = 'Inspirational Resources';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Submissions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return ResourceSubmissionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResourceSubmissionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResourceSubmissions::route('/'),
            'view' => ViewResourceSubmission::route('/{record}'),
        ];
    }

    public static function markInReviewAction(): Action
    {
        return Action::make('markInReview')
            ->label('Mark In Review')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->color('warning')
            ->visible(fn (ResourceSubmission $record): bool => $record->status === ResourceSubmissionStatus::Submitted)
            ->action(function (ResourceSubmission $record): void {
                app(MarkResourceSubmissionInReviewAction::class)->handle($record, self::actor());

                Notification::make()->title('Marked in review')->success()->send();
            });
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (ResourceSubmission $record): bool => in_array($record->status, [ResourceSubmissionStatus::Submitted, ResourceSubmissionStatus::InReview], true))
            ->requiresConfirmation()
            ->action(function (ResourceSubmission $record): void {
                app(ApproveResourceSubmissionAction::class)->handle($record, self::actor());

                Notification::make()->title('Submission approved')->success()->send();
            });
    }

    public static function archiveAction(): Action
    {
        return Action::make('archive')
            ->label('Archive')
            ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->color('danger')
            ->visible(fn (ResourceSubmission $record): bool => $record->status !== ResourceSubmissionStatus::Archived)
            ->requiresConfirmation()
            ->action(function (ResourceSubmission $record): void {
                app(ArchiveResourceSubmissionAction::class)->handle($record, self::actor());

                Notification::make()->title('Submission archived')->success()->send();
            });
    }

    /**
     * Only meaningful once Approved — that's the only status with a live
     * public URL (see ResourceSubmission's docblock).
     */
    public static function viewPublicPageAction(): Action
    {
        return Action::make('viewPublicPage')
            ->label('View Public Page')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->visible(fn (ResourceSubmission $record): bool => $record->status === ResourceSubmissionStatus::Approved)
            ->url(fn (ResourceSubmission $record): string => route('inspirational-resources.show', $record))
            ->openUrlInNewTab();
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = Auth::user();

        return $actor;
    }
}
