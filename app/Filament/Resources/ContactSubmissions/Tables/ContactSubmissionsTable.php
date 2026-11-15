<?php

namespace App\Filament\Resources\ContactSubmissions\Tables;

use App\Actions\Contact\BanContactSendersAction;
use App\Filament\Resources\ContactSubmissions\ContactSubmissionResource;
use App\Models\ContactBan;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * List-only (mirrors ResourceSubmissionsTable) — submissions are created
 * exclusively by SubmitContactFormAction from the public form, never
 * hand-created in the admin panel. Bulk actions: Ban senders (blocks the
 * selected senders' IP / email / name from submitting again — see
 * ContactBan) and Delete, for spam that slips past the other layers.
 */
class ContactSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->placeholder('—'),
                TextColumn::make('message')->limit(60)->wrap(),
                // Search by IP to find (and bulk-delete) everything from one
                // spam source; empty for submissions made before IPs were recorded.
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->searchable()
            ->recordActions([
                ViewAction::make(),
                ContactSubmissionResource::replyAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::banSendersBulkAction(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No messages yet')
            ->emptyStateDescription('New Contact Us submissions from the public form will appear here.')
            ->emptyStateIcon('heroicon-o-envelope');
    }

    /**
     * Each ticked criterion becomes its own ban, and a future submission is
     * blocked when ANY of its IP / email / name matches one. Name is off by
     * default: a common name could block genuine visitors.
     */
    private static function banSendersBulkAction(): BulkAction
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
                Toggle::make('delete_submissions')
                    ->label('Also delete the selected submissions')
                    ->default(true),
            ])
            ->action(function (Collection $records, array $data, BanContactSendersAction $banSenders, Component $livewire): void {
                /** @var User|null $actor */
                $actor = Auth::user();

                $created = $banSenders->handle(
                    $records,
                    $data['criteria'] ?? [],
                    $data['reason'] ?? null,
                    $actor,
                    (bool) ($data['delete_submissions'] ?? false),
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
}
