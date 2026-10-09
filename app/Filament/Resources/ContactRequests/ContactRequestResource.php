<?php

namespace App\Filament\Resources\ContactRequests;

use App\Actions\Contact\DeleteContactRequestAction;
use App\Actions\Contact\SubmitContactRequestAction;
use App\Actions\Contact\UpdateContactRequestAction;
use App\Enums\ContactRequestStatus;
use App\Filament\Resources\ContactRequests\Pages\ListContactRequests;
use App\Filament\Resources\ContactRequests\Pages\ViewContactRequest;
use App\Filament\Resources\ContactRequests\Schemas\ContactRequestInfolist;
use App\Filament\Resources\ContactRequests\Tables\ContactRequestsTable;
use App\Models\ContactRequest;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use App\Shared\Services\Settings\SettingsRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * ADMIN-010 — the Core admin UI for public Contact Us submissions
 * (App\Models\ContactRequest, DB-002/003). List-only + View, matching the
 * approved scope: a submission's own content is never admin-editable, only
 * its workflow fields (status/resolution notes), changed via updateAction()
 * below rather than a full Edit page/form.
 */
class ContactRequestResource extends Resource
{
    protected static ?string $model = ContactRequest::class;

    protected static string|UnitEnum|null $navigationGroup = 'Submissions';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return ContactRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactRequests::route('/'),
            'view' => ViewContactRequest::route('/{record}'),
        ];
    }

    /**
     * Reused by the List row actions and the View page header — the only
     * way a contact request's status/resolution notes change, so the audit
     * trail (via UpdateContactRequestAction) is always applied.
     */
    public static function updateAction(): Action
    {
        return Action::make('update')
            ->label('Update Status & Notes')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->schema([
                Select::make('status')
                    ->label('Status')
                    ->options(ContactRequestStatus::options())
                    ->required(),
                Textarea::make('resolution_notes')
                    ->label('Resolution Notes')
                    ->rows(4)
                    ->maxLength(5000),
            ])
            ->fillForm(fn (ContactRequest $record): array => [
                'status' => $record->status->value,
                'resolution_notes' => $record->resolution_notes,
            ])
            ->action(function (ContactRequest $record, array $data): void {
                /** @var User $actor */
                $actor = Auth::user();

                app(UpdateContactRequestAction::class)->handle($record, $data, $actor);

                Notification::make()
                    ->title('Contact request updated')
                    ->success()
                    ->send();
            });
    }

    /**
     * Sends a submission's emails again — for messages whose emails failed
     * when they arrived (e.g. a mail-server outage). Reused by the List row
     * actions and the View page header; resendBulkAction() does the same
     * for a selection. Each resend is audit-logged.
     */
    public static function resendAction(): Action
    {
        return Action::make('resendEmails')
            ->label('Resend emails')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->modalHeading(fn (ContactRequest $record): string => "Resend emails for \"{$record->name}\"")
            ->modalDescription('Sends the "contact form submitted" emails again, using the current Email Templates and email settings.')
            ->modalSubmitActionLabel('Resend')
            ->schema(self::resendSchema())
            ->fillForm(['recipients' => ['submitter', 'admins']])
            ->action(fn (ContactRequest $record, array $data) => self::resend(collect([$record]), $data['recipients'] ?? []));
    }

    public static function resendBulkAction(): BulkAction
    {
        return BulkAction::make('resendEmails')
            ->label('Resend emails')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->modalHeading('Resend emails for the selected messages')
            ->modalDescription('Sends the "contact form submitted" emails again for each selected message, using the current Email Templates and email settings.')
            ->modalSubmitActionLabel('Resend')
            ->schema(self::resendSchema())
            ->fillForm(['recipients' => ['submitter', 'admins']])
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records, array $data) => self::resend($records, $data['recipients'] ?? []));
    }

    /**
     * @return array<int, CheckboxList>
     */
    private static function resendSchema(): array
    {
        return [
            CheckboxList::make('recipients')
                ->label('Send to')
                ->options([
                    'submitter' => 'The visitor (their receipt)',
                    'admins' => 'Admins (new-message notification)',
                ])
                ->required()
                ->minItems(1),
        ];
    }

    /**
     * @param  Collection<int, ContactRequest>  $records
     * @param  array<int, string>  $recipients
     */
    private static function resend(Collection $records, array $recipients): void
    {
        if (! app(SettingsRepository::class)->get('email', 'enabled', false)) {
            Notification::make()
                ->title('Email sending is switched off')
                ->body('Turn on "Enable Email Sending" in Website Setup → Settings → Email first. Nothing was sent.')
                ->warning()
                ->send();

            return;
        }

        /** @var User $actor */
        $actor = Auth::user();
        $submitter = in_array('submitter', $recipients, true);
        $admins = in_array('admins', $recipients, true);
        $total = ['sent' => 0, 'failed' => 0];

        foreach ($records as $record) {
            $result = app(SubmitContactRequestAction::class)->notify($record, $submitter, $admins);
            $total['sent'] += $result['sent'];
            $total['failed'] += $result['failed'];

            app(AuditLogService::class)->record($actor, 'contact_request.emails_resent', $record, [
                'recipients' => array_values($recipients),
                ...$result,
            ]);
        }

        $sent = $total['sent'].' '.str('email')->plural($total['sent']).' sent';

        if ($total['failed'] > 0) {
            Notification::make()
                ->title($total['sent'] > 0 ? "{$sent}, {$total['failed']} failed" : 'The emails could not be sent')
                ->body('Check the mail server settings in Website Setup → Settings → Email. The details are in the application log.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()->title($sent)->success()->send();
    }

    /**
     * Soft-deletes — see DeleteContactRequestAction. No "still in use"
     * guard is needed (nothing else references contact_requests rows).
     */
    public static function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (ContactRequest $record): string => "Delete the message from \"{$record->name}\"?")
            ->action(function (ContactRequest $record): void {
                /** @var User $actor */
                $actor = Auth::user();

                app(DeleteContactRequestAction::class)->handle($record, $actor);

                Notification::make()
                    ->title('Contact request deleted')
                    ->success()
                    ->send();
            });
    }
}
