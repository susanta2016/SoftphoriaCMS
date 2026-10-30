<?php

namespace App\Modules\Newsletter\Filament\Resources\Subscribers\Schemas;

use App\Models\NewsletterSubscriber;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Support\NewsletterFeature;
use App\Modules\Newsletter\Support\SuppressionList;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NewsletterSubscriberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Subscriber')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('email')->copyable(),
                        TextEntry::make('full_name')
                            ->label('Name')
                            ->state(fn (NewsletterSubscriber $record): string => $record->fullName())
                            ->placeholder('—'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => SubscriberStatus::labelFor($state))
                            ->color(fn (?string $state): string => SubscriberStatus::colorFor($state)),
                        TextEntry::make('groups.name')->label('Groups')->badge()->placeholder('No groups')
                            ->visible(fn (): bool => NewsletterFeature::enabled()),
                        TextEntry::make('source')->placeholder('—'),
                        TextEntry::make('created_at')->label('Added')->dateTime(),
                    ]),

                Section::make('Consent')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('consented_at')->label('Consent given')->dateTime()->placeholder('—'),
                        TextEntry::make('confirmed_at')->label('Double opt-in confirmed')->dateTime()->placeholder('—'),
                        TextEntry::make('unsubscribed_at')->label('Unsubscribed')->dateTime()->placeholder('—'),
                    ]),

                Section::make('Suppression')
                    ->columns(3)
                    ->visible(fn (NewsletterSubscriber $record): bool => app(SuppressionList::class)->isSuppressed($record->email))
                    ->schema([
                        TextEntry::make('suppression_reason')
                            ->label('Reason')
                            ->state(fn (NewsletterSubscriber $record): ?string => app(SuppressionList::class)->find($record->email)?->reason->getLabel()),
                        TextEntry::make('suppression_detail')
                            ->label('Detail')
                            ->state(fn (NewsletterSubscriber $record): ?string => app(SuppressionList::class)->find($record->email)?->detail)
                            ->placeholder('—'),
                        TextEntry::make('suppression_at')
                            ->label('Since')
                            ->state(fn (NewsletterSubscriber $record) => app(SuppressionList::class)->find($record->email)?->created_at)
                            ->dateTime(),
                    ]),

                Section::make('Last provider event')
                    ->columns(3)
                    ->collapsed()
                    ->visible(fn (NewsletterSubscriber $record): bool => $record->ses_event_at !== null)
                    ->schema([
                        TextEntry::make('ses_event_type')->label('Type'),
                        TextEntry::make('ses_event_reason')->label('Reason')->placeholder('—'),
                        TextEntry::make('ses_event_at')->label('At')->dateTime(),
                    ]),

                Section::make('Additional profile fields')
                    ->collapsed()
                    ->visible(fn (NewsletterSubscriber $record): bool => filled($record->profile_fields))
                    ->schema([
                        KeyValueEntry::make('profile_fields')->hiddenLabel(),
                    ]),
            ]);
    }
}
