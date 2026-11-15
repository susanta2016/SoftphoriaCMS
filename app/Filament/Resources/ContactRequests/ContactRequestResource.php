<?php

namespace App\Filament\Resources\ContactRequests;

use App\Actions\Contact\BanContactSendersAction;
use App\Actions\Contact\DeleteContactRequestAction;
use App\Actions\Contact\SubmitContactRequestAction;
use App\Actions\Contact\UpdateContactRequestAction;
use App\Enums\ContactRequestStatus;
use App\Filament\Resources\ContactRequests\Pages\ListContactRequests;
use App\Filament\Resources\ContactRequests\Pages\ViewContactRequest;
use App\Filament\Resources\ContactRequests\Schemas\ContactRequestInfolist;
use App\Filament\Resources\ContactRequests\Tables\ContactRequestsTable;
use App\Models\ContactBan;
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
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
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
    /**
     * Each ticked criterion becomes its own ContactBan, and a future
     * submission is blocked when ANY of its IP / email / name matches one.
     * Name is off by default: a common name could block genuine visitors.
     * Bans are listed and lifted in the "Banned senders" section below the
     * table (Widgets\BannedSendersWidget).
     */
    public static function banSendersBulkAction(): BulkAction
    {
        return BulkAction::make('banSenders')
            ->label('Ban senders')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->modalHeading('Ban the selected senders')
            ->modalDescription('Banned senders can no longer submit the Contact Us form — their attempts are silently discarded. Review or lift bans in the "Banned senders" section at the bottom of this page.')
            ->modalSubmitActionLabel('Ban')
            ->schema([
                CheckboxList::make('criteria')
                    ->label('Ban by')
                    ->options(ContactBan::TYPES)
                    ->descriptions([
                        ContactBan::TYPE_IP => 'Blocks every submission from the same IP address.',
                        ContactBan::TYPE_EMAIL => 'Blocks every submission using the same email address.',
                        ContactBan::TYPE_NAME => 'Blocks every submission using the exact same name — careful with common names.',
                    ])
                    ->default([ContactBan::TYPE_IP, ContactBan::TYPE_EMAIL])
                    ->required(),
                TextInput::make('reason')
                    ->label('Reason (optional)')
                    ->default('Spam')
                    ->maxLength(255),
                Toggle::make('delete_requests')
                    ->label('Also delete the selected messages')
                    ->default(true),
            ])
            ->action(function (Collection $records, array $data, Component $livewire): void {
                /** @var User $actor */
                $actor = Auth::user();

                $created = app(BanContactSendersAction::class)->handle(
                    $records,
                    $data['criteria'] ?? [],
                    $data['reason'] ?? null,
                    $actor,
                    (bool) ($data['delete_requests'] ?? false),
                );

                // Refreshes the "Banned senders" section below the table.
                $livewire->dispatch('contact-bans-updated');

                Notification::make()
                    ->success()
                    ->title($created === 1 ? '1 new ban added' : "{$created} new bans added")
                    ->body($created === 0 ? 'The selected senders were already banned.' : null)
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Bulk counterpart of deleteAction(): every row goes through
     * DeleteContactRequestAction (soft delete + audit entry), never a raw
     * query delete.
     */
    public static function deleteBulkAction(): BulkAction
    {
        return BulkAction::make('deleteSelected')
            ->label('Delete selected')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete the selected messages?')
            ->modalSubmitActionLabel('Delete')
            ->action(function (Collection $records): void {
                /** @var User $actor */
                $actor = Auth::user();
                $action = app(DeleteContactRequestAction::class);

                $records->each(fn (ContactRequest $record) => $action->handle($record, $actor));

                Notification::make()
                    ->title($records->count() === 1 ? '1 message deleted' : "{$records->count()} messages deleted")
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

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
