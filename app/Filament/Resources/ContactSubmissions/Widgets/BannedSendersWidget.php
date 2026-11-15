<?php

namespace App\Filament\Resources\ContactSubmissions\Widgets;

use App\Models\ContactBan;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

/**
 * "Banned senders" section shown under the submissions table on
 * Contact → Submissions (ListContactSubmissions::getFooterWidgets()) — the
 * admin asked for bans to be managed on that same page, not a separate menu
 * item. Lists every ContactBan; Add ban bans a value by hand, Unban deletes
 * the row. Lives under the resource (not app/Filament/Widgets) so it is
 * never auto-discovered onto the dashboard.
 */
class BannedSendersWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /** Fired by the "Ban senders" bulk action so this list refreshes. */
    #[On('contact-bans-updated')]
    public function refreshBans(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->query(ContactBan::query()->with('bannedBy'))
            ->heading('Banned senders')
            ->description('These IP addresses, emails and names can no longer submit the Contact Us form.')
            ->columns([
                TextColumn::make('type')
                    ->label('Ban by')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ContactBan::TYPES[$state] ?? $state),
                TextColumn::make('value')->searchable()->copyable(),
                TextColumn::make('reason')->placeholder('—')->limit(40),
                TextColumn::make('bannedBy.name')->label('Banned by')->placeholder('—'),
                TextColumn::make('created_at')->label('Banned')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Ban by')->options(ContactBan::TYPES),
            ])
            ->headerActions([
                self::addBanAction(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Unban')
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->modalHeading('Lift this ban?')
                    ->successNotificationTitle('Ban lifted'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Unban selected')
                        ->successNotificationTitle('Bans lifted'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('No banned senders')
            ->emptyStateDescription('Select spam messages above and use Bulk actions → Ban senders.')
            ->emptyStateIcon('heroicon-o-no-symbol');
    }

    /**
     * firstOrCreate on the normalised value: re-banning an existing value is
     * a no-op rather than a unique-constraint error.
     */
    private static function addBanAction(): Action
    {
        return Action::make('addBan')
            ->label('Add ban')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->modalHeading('Ban a sender')
            ->modalSubmitActionLabel('Ban')
            ->schema([
                Select::make('type')
                    ->label('Ban by')
                    ->options(ContactBan::TYPES)
                    ->required(),
                TextInput::make('value')
                    ->label('IP address, email or name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('reason')
                    ->label('Reason (optional)')
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                $ban = ContactBan::query()->firstOrCreate(
                    ['type' => $data['type'], 'value' => ContactBan::normalize($data['type'], $data['value'])],
                    ['reason' => $data['reason'] ?? null, 'banned_by' => Auth::id()],
                );

                Notification::make()
                    ->success()
                    ->title($ban->wasRecentlyCreated ? 'Sender banned' : 'Already banned')
                    ->send();
            });
    }
}
