<?php

namespace App\Modules\Commerce\Filament\Resources\Orders\Schemas;

use App\Modules\Commerce\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * §1 of the approved brief: everything Admin needs to see about one order —
 * purchaser, item(s) with their historical price snapshot, payment/
 * transaction reference, and entitlement/download status. Read-only; the
 * one mutation available from here is OrderResource::revokeAccessAction()
 * on the header of ViewOrder.
 */
class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('public_id')->label('Order #')->copyable(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('created_at')->label('Placed')->dateTime(),
                        TextEntry::make('purchaser_email')->label('Purchaser email')->copyable(),
                        TextEntry::make('purchaser_name')->label('Purchaser name')->placeholder('—'),
                        TextEntry::make('user.name')
                            ->label('Purchaser type')
                            ->formatStateUsing(fn (?string $state): string => $state === null ? 'Guest' : "Registered ({$state})"),
                        TextEntry::make('total')->money(fn (Order $record): string => $record->currency),
                        TextEntry::make('payment_provider')->label('Provider'),
                        TextEntry::make('paid_at')->dateTime()->placeholder('Not paid'),
                        TextEntry::make('stripe_checkout_session_id')->label('Checkout session')->copyable()->placeholder('—'),
                        TextEntry::make('stripe_payment_intent_id')->label('Payment intent')->copyable()->placeholder('—'),
                    ]),

                Section::make('Item(s) purchased')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('item_title')->label('Item'),
                                TextEntry::make('quantity'),
                                TextEntry::make('unit_price')->label('Price at purchase')->money(fn ($record) => $record->currency),
                                TextEntry::make('total')->money(fn ($record) => $record->currency),
                            ]),
                    ]),

                Section::make('Entitlement')
                    ->columns(5)
                    ->schema([
                        TextEntry::make('entitlement.public_id')
                            ->label('Entitlement #')
                            ->state(fn (Order $record): ?string => $record->items->first()?->entitlement?->public_id)
                            ->copyable(),
                        TextEntry::make('entitlement.downloads_used')
                            ->label('Downloads used')
                            ->state(fn (Order $record): ?int => $record->items->first()?->entitlement?->downloads_used),
                        TextEntry::make('entitlement.max_downloads')
                            ->label('Limit')
                            ->state(fn (Order $record): ?int => $record->items->first()?->entitlement?->max_downloads)
                            ->placeholder('Unlimited'),
                        TextEntry::make('entitlement.expires_at')
                            ->label('Expires')
                            ->state(fn (Order $record): ?string => $record->items->first()?->entitlement?->expires_at?->toDateTimeString())
                            ->placeholder('Never'),
                        TextEntry::make('entitlement.revoked_at')
                            ->label('Revoked')
                            ->state(fn (Order $record): ?string => $record->items->first()?->entitlement?->revoked_at?->toDateTimeString())
                            ->placeholder('No'),
                    ])
                    ->visible(fn (Order $record): bool => $record->items->first()?->entitlement !== null),

                Section::make('Payment transactions')
                    ->schema([
                        RepeatableEntry::make('transactions')
                            ->hiddenLabel()
                            ->columns(5)
                            ->schema([
                                TextEntry::make('type')->badge(),
                                TextEntry::make('status')->badge(),
                                TextEntry::make('amount')->money(fn ($record) => $record->currency)->placeholder('—'),
                                TextEntry::make('provider_reference')->label('Reference')->copyable()->placeholder('—'),
                                TextEntry::make('occurred_at')->dateTime(),
                            ]),
                    ])
                    ->visible(fn (Order $record): bool => $record->transactions->isNotEmpty()),
            ]);
    }
}
