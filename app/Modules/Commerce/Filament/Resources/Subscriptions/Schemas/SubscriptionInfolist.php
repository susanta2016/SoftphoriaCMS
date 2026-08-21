<?php

namespace App\Modules\Commerce\Filament\Resources\Subscriptions\Schemas;

use App\Modules\Commerce\Models\Subscription;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubscriptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Subscription')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('user.name')->label('User'),
                        TextEntry::make('user.email')->label('Email')->copyable(),
                        TextEntry::make('display_status')
                            ->label('Status')
                            ->getStateUsing(fn (Subscription $record): string => $record->displayStatus()->getLabel())
                            ->badge()
                            ->color(fn (Subscription $record): string => $record->displayStatus()->getColor()),
                        TextEntry::make('status')->label('Stripe status')->badge(),
                        TextEntry::make('price_at_subscription')->label('Price at signup')->money('usd')->placeholder('—'),
                        TextEntry::make('started_at')->dateTime()->placeholder('—'),
                        TextEntry::make('current_period_start')->label('Period start')->dateTime()->placeholder('—'),
                        TextEntry::make('current_period_end')->label('Period end / next renewal')->dateTime()->placeholder('—'),
                        TextEntry::make('cancel_at_period_end')->label('Cancelling at period end')->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                        TextEntry::make('cancelled_at')->dateTime()->placeholder('—'),
                        TextEntry::make('ended_at')->dateTime()->placeholder('—'),
                    ]),

                Section::make('Stripe references')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('stripe_customer_id')->label('Customer ID')->copyable()->placeholder('—'),
                        TextEntry::make('stripe_subscription_id')->label('Subscription ID')->copyable()->placeholder('—'),
                    ]),

                Section::make('Last payment')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('lastStatus')
                            ->label('Status')
                            ->getStateUsing(fn (Subscription $record): string => $record->latestTransaction()?->status?->getLabel() ?? 'No transactions yet'),
                        TextEntry::make('lastAmount')
                            ->label('Amount')
                            ->getStateUsing(fn (Subscription $record): ?string => $record->latestTransaction()?->amount !== null ? '$'.$record->latestTransaction()->amount : null)
                            ->placeholder('—'),
                        TextEntry::make('lastOccurredAt')
                            ->label('Occurred')
                            ->getStateUsing(fn (Subscription $record): ?string => $record->latestTransaction()?->occurred_at?->toDateTimeString())
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
