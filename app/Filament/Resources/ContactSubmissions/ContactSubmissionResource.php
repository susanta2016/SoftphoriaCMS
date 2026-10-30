<?php

namespace App\Filament\Resources\ContactSubmissions;

use App\Actions\Contact\SendContactSubmissionReplyAction;
use App\Enums\ContactSubmissionReplyStatus;
use App\Exceptions\Contact\ContactSubmissionReplyException;
use App\Filament\Resources\ContactSubmissions\Pages\ListContactSubmissions;
use App\Filament\Resources\ContactSubmissions\Pages\ViewContactSubmission;
use App\Filament\Resources\ContactSubmissions\Schemas\ContactSubmissionInfolist;
use App\Filament\Resources\ContactSubmissions\Tables\ContactSubmissionsTable;
use App\Models\ContactSubmission;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * List-only + View — submissions are created exclusively by
 * SubmitContactFormAction from the public Contact Us form, never
 * hand-built in the admin panel (mirrors ResourceSubmissionResource's
 * exact reasoning). No status/workflow, unlike ResourceSubmission — a
 * contact message is a plain inbox entry, not a review queue. The only
 * admin-initiated operation is the optional Reply (replyAction()), which
 * never modifies the submission itself.
 */
class ContactSubmissionResource extends Resource
{
    protected static ?string $model = ContactSubmission::class;

    protected static string|UnitEnum|null $navigationGroup = 'Contact';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Submissions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return ContactSubmissionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactSubmissionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactSubmissions::route('/'),
            'view' => ViewContactSubmission::route('/{record}'),
        ];
    }

    /**
     * Admin "Reply" (config('features.contact_submission_reply_enabled')),
     * on both the table row and the View page. Two steps — compose, then
     * confirm — before anything is sent. The recipient is shown, never
     * editable: SendContactSubmissionReplyAction always uses the stored
     * address and re-checks the flag/admin/input itself. The hidden
     * idempotency key is minted once per opened form, so a double-click or a
     * retry after a failure never sends twice; on failure the modal stays
     * open (halt()) with the text intact for a retry.
     */
    public static function replyAction(): Action
    {
        return Action::make('reply')
            ->label('Reply')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('primary')
            ->visible(fn (): bool => SendContactSubmissionReplyAction::isEnabled())
            ->modalHeading(fn (ContactSubmission $record): string => "Reply to {$record->name}")
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Send Reply')
            ->fillForm(fn (): array => [
                'subject' => app(SendContactSubmissionReplyAction::class)->defaultSubject(),
                'message' => '',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->steps([
                Step::make('Compose')
                    ->schema([
                        TextEntry::make('reply_to')
                            ->label('To')
                            ->state(fn (ContactSubmission $record): string => "{$record->name} <{$record->email}>"),
                        TextEntry::make('original_message')
                            ->label(fn (ContactSubmission $record): string => 'Their message ('.$record->created_at->format('M j, Y g:i A').')')
                            ->state(fn (ContactSubmission $record): string => $record->message)
                            ->extraAttributes(['class' => 'whitespace-pre-line']),
                        TextInput::make('subject')
                            ->required()
                            ->maxLength(SendContactSubmissionReplyAction::SUBJECT_MAX_LENGTH)
                            ->rules(['not_regex:/[\r\n]/']),
                        Textarea::make('message')
                            ->label('Reply')
                            ->required()
                            ->rows(8)
                            ->maxLength(SendContactSubmissionReplyAction::MESSAGE_MAX_LENGTH),
                        Hidden::make('idempotency_key'),
                    ]),
                Step::make('Confirm')
                    ->description('Check before sending')
                    ->schema([
                        TextEntry::make('confirm_to')
                            ->label('To')
                            ->state(fn (ContactSubmission $record): string => $record->email),
                        TextEntry::make('confirm_subject')
                            ->label('Subject')
                            ->state(fn (Get $get): string => (string) $get('subject')),
                        TextEntry::make('confirm_message')
                            ->label('Reply')
                            ->state(fn (Get $get): string => (string) $get('message'))
                            ->extraAttributes(['class' => 'whitespace-pre-line']),
                    ]),
            ])
            ->action(function (ContactSubmission $record, array $data, Action $action): void {
                /** @var User $admin */
                $admin = Auth::user();

                try {
                    $reply = app(SendContactSubmissionReplyAction::class)->handle(
                        $record,
                        $admin,
                        (string) ($data['subject'] ?? ''),
                        (string) ($data['message'] ?? ''),
                        (string) ($data['idempotency_key'] ?? ''),
                    );
                } catch (ContactSubmissionReplyException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    $action->halt();

                    return;
                }

                $record->unsetRelation('replies');

                if ($reply->status === ContactSubmissionReplyStatus::Sent) {
                    Notification::make()->title("Reply sent to {$reply->recipient_email}.")->success()->send();

                    return;
                }

                Notification::make()->title('This reply is already being sent.')->warning()->send();
            });
    }
}
