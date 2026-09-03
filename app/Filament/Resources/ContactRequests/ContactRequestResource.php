<?php

namespace App\Filament\Resources\ContactRequests;

use App\Actions\Contact\DeleteContactRequestAction;
use App\Actions\Contact\UpdateContactRequestAction;
use App\Enums\ContactRequestStatus;
use App\Filament\Resources\ContactRequests\Pages\ListContactRequests;
use App\Filament\Resources\ContactRequests\Pages\ViewContactRequest;
use App\Filament\Resources\ContactRequests\Schemas\ContactRequestInfolist;
use App\Filament\Resources\ContactRequests\Tables\ContactRequestsTable;
use App\Models\ContactRequest;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
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
