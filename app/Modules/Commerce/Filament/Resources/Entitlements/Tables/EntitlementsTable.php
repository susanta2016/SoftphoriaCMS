<?php

namespace App\Modules\Commerce\Filament\Resources\Entitlements\Tables;

use App\Modules\Commerce\Enums\EntitlementStatus;
use App\Modules\Commerce\Models\Entitlement;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * §3/§16: "Entitlements / Purchases" — every row here is a Single/Album
 * purchase grant. Pro Member (subscription) access is intentionally not
 * listed here — see SubscriptionsTable and Subscription's docblock for why.
 */
class EntitlementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')->label('Entitlement #')->searchable()->copyable(),
                TextColumn::make('purchaser_email')
                    ->label('Purchaser')
                    ->searchable()
                    ->description(fn (Entitlement $record): string => $record->isGuest() ? 'Guest' : 'Registered'),
                TextColumn::make('album.title')
                    ->label('Item')
                    ->getStateUsing(fn (Entitlement $record): string => $record->album?->title ?? $record->single?->title ?? $record->track?->title ?? '—'),
                TextColumn::make('computed_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (Entitlement $record): string => $record->status()->value)
                    ->formatStateUsing(fn (string $state): string => EntitlementStatus::from($state)->getLabel())
                    ->color(fn (string $state): string => EntitlementStatus::from($state)->getColor()),
                TextColumn::make('created_at')->label('Granted')->dateTime()->sortable(),
                TextColumn::make('expires_at')->dateTime()->placeholder('Never')->sortable(),
                TextColumn::make('downloads_used')->label('Used')->sortable(),
                TextColumn::make('max_downloads')->label('Limit')->placeholder('Unlimited'),
                TextColumn::make('remaining')
                    ->getStateUsing(fn (Entitlement $record): string => $record->remainingDownloads() === null ? 'Unlimited' : (string) $record->remainingDownloads()),
            ])
            ->filters([
                SelectFilter::make('computed_status')
                    ->label('Status')
                    ->options(collect(EntitlementStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()]))
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'revoked' => $query->whereNotNull('revoked_at'),
                            'expired' => $query->whereNull('revoked_at')->whereNotNull('expires_at')->where('expires_at', '<', now()),
                            'exhausted' => $query->whereNull('revoked_at')->whereColumn('downloads_used', '>=', 'max_downloads'),
                            'active' => $query->whereNull('revoked_at')
                                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
                                ->where(fn ($q) => $q->whereNull('max_downloads')->orWhereColumn('downloads_used', '<', 'max_downloads')),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25);
    }
}
