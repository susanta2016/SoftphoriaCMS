<?php

namespace App\Modules\Newsletter\Filament\Resources\Subscribers\Tables;

use App\Models\NewsletterSubscriber;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Models\NewsletterGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class NewsletterSubscribersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('groups'))
            ->columns([
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label('Name')
                    ->state(fn (NewsletterSubscriber $record): string => $record->fullName())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query) => $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")))
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => SubscriberStatus::labelFor($state))
                    ->color(fn (?string $state): string => SubscriberStatus::colorFor($state))
                    ->sortable(),
                TextColumn::make('groups.name')
                    ->label('Groups')
                    ->badge()
                    ->limitList(3)
                    ->placeholder('—'),
                TextColumn::make('source')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('consented_at')
                    ->label('Consent')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(SubscriberStatus::cases())->mapWithKeys(fn (SubscriberStatus $status): array => [$status->value => $status->getLabel()])->all()),
                SelectFilter::make('groups')
                    ->relationship('groups', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('addToGroup')
                        ->label('Add to group')
                        ->icon(Heroicon::OutlinedUserPlus)
                        ->schema([self::groupSelect()])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (NewsletterSubscriber $subscriber) => $subscriber->groups()->syncWithoutDetaching([(int) $data['group_id']]));
                            Notification::make()->title('Added '.$records->count().' subscriber(s) to the group.')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('removeFromGroup')
                        ->label('Remove from group')
                        ->icon(Heroicon::OutlinedUserMinus)
                        ->schema([self::groupSelect()])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (NewsletterSubscriber $subscriber) => $subscriber->groups()->detach((int) $data['group_id']));
                            Notification::make()->title('Removed '.$records->count().' subscriber(s) from the group.')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->modalDescription('Deleting keeps their campaign history and any suppression — a suppressed address stays blocked if it is added again.'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No subscribers yet')
            ->emptyStateDescription('People who sign up through the website footer appear here. You can also add or import subscribers.');
    }

    private static function groupSelect(): Select
    {
        return Select::make('group_id')
            ->label('Group')
            ->options(fn (): array => NewsletterGroup::query()->orderBy('name')->pluck('name', 'id')->all())
            ->searchable()
            ->required();
    }
}
