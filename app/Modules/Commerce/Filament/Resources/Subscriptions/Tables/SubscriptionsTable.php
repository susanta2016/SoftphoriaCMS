<?php

namespace App\Modules\Commerce\Filament\Resources\Subscriptions\Tables;

use App\Modules\Commerce\Enums\SubscriptionDisplayStatus;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\Subscription;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * §4/§12: Subscription admin visibility, fully read-only (no Edit page
 * exists at all — see SubscriptionResource's docblock for why that's the
 * deliberate answer to "do not allow the Admin UI to manually put the
 * application into a state that contradicts Stripe").
 */
class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('User')->searchable(),
                TextColumn::make('user.email')->label('Email')->searchable(),
                TextColumn::make('display_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (Subscription $record): string => $record->displayStatus()->value)
                    ->formatStateUsing(fn (string $state): string => SubscriptionDisplayStatus::from($state)->getLabel())
                    ->color(fn (string $state): string => SubscriptionDisplayStatus::from($state)->getColor()),
                TextColumn::make('status')
                    ->label('Stripe status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('price_at_subscription')->label('Price')->money('usd')->placeholder('—'),
                TextColumn::make('current_period_start')->label('Period start')->dateTime()->placeholder('—'),
                TextColumn::make('current_period_end')->label('Period end / renews')->dateTime()->sortable()->placeholder('—'),
                TextColumn::make('last_payment_status')
                    ->label('Last payment')
                    ->getStateUsing(fn (Subscription $record): string => $record->latestTransaction()?->status?->getLabel() ?? '—'),
            ])
            ->filters([
                SelectFilter::make('display_status')
                    ->label('Status')
                    ->options(collect(SubscriptionDisplayStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()]))
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'active' => $query->where('status', SubscriptionStatus::Active->value)->where('cancel_at_period_end', false)
                                ->where(fn ($q) => $q->whereNull('current_period_end')->orWhere('current_period_end', '>=', now())),
                            'canceling_at_period_end' => $query->where('status', SubscriptionStatus::Active->value)->where('cancel_at_period_end', true)
                                ->where(fn ($q) => $q->whereNull('current_period_end')->orWhere('current_period_end', '>=', now())),
                            'payment_problem' => $query->whereIn('status', [SubscriptionStatus::PastDue->value, SubscriptionStatus::Unpaid->value, SubscriptionStatus::Incomplete->value]),
                            'expired' => $query->where(fn ($q) => $q->where('status', '!=', SubscriptionStatus::Active->value)
                                ->orWhere('current_period_end', '<', now()))
                                ->whereNotIn('status', [SubscriptionStatus::PastDue->value, SubscriptionStatus::Unpaid->value, SubscriptionStatus::Incomplete->value]),
                            default => $query,
                        };
                    }),
                SelectFilter::make('status')->label('Stripe status')->options(SubscriptionStatus::options()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('current_period_end', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25);
    }
}
