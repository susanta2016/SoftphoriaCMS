<?php

namespace App\Modules\Newsletter\Filament\Resources\Groups\RelationManagers;

use App\Models\NewsletterSubscriber;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Filament\Resources\Subscribers\NewsletterSubscriberResource;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * A group's members. Adding someone here only changes membership — an
 * unsubscribed or suppressed member stays unsendable (shown by Status).
 */
class SubscribersRelationManager extends RelationManager
{
    protected static string $relationship = 'subscribers';

    // NewsletterSubscriber::groups(). Filament would otherwise guess
    // "newsletterGroups" from the owner model name for the Attach action.
    protected static ?string $inverseRelationship = 'groups';

    protected static ?string $title = 'Members';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                TextColumn::make('email')->searchable()->sortable(),
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
                    ->color(fn (?string $state): string => SubscriberStatus::colorFor($state)),
                TextColumn::make('pivot.created_at')->label('Added to group')->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(SubscriberStatus::cases())->mapWithKeys(fn (SubscriberStatus $status): array => [$status->value => $status->getLabel()])->all()),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Add subscribers')
                    ->multiple()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['email', 'first_name', 'last_name']),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (NewsletterSubscriber $record): string => NewsletterSubscriberResource::getUrl('view', ['record' => $record])),
                DetachAction::make()->label('Remove'),
            ])
            ->toolbarActions([
                DetachBulkAction::make()->label('Remove from group'),
            ])
            ->defaultSort('email');
    }
}
