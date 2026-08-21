<?php

namespace App\Modules\Commerce\Filament\Resources\Entitlements\Schemas;

use App\Modules\Commerce\Models\Entitlement;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EntitlementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Entitlement')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('public_id')->label('Entitlement #')->copyable(),
                        TextEntry::make('computed_status')
                            ->label('Status')
                            ->getStateUsing(fn (Entitlement $record): string => $record->status()->getLabel())
                            ->badge()
                            ->color(fn (Entitlement $record): string => $record->status()->getColor()),
                        TextEntry::make('created_at')->label('Granted')->dateTime(),
                        TextEntry::make('purchaser_email')->label('Purchaser')->copyable(),
                        TextEntry::make('user.name')->label('Account')->placeholder('Guest (no account)'),
                        TextEntry::make('item')
                            ->label('Item')
                            ->getStateUsing(fn (Entitlement $record): string => ($record->album?->title ?? $record->single?->title ?? '—').' ('.($record->album_id !== null ? 'Album' : 'Single').')'),
                        TextEntry::make('expires_at')->dateTime()->placeholder('Never'),
                        TextEntry::make('downloads_used')->label('Downloads used'),
                        TextEntry::make('max_downloads')->label('Limit')->placeholder('Unlimited'),
                        TextEntry::make('revoked_at')->dateTime()->placeholder('No'),
                        TextEntry::make('revoked_reason')->label('Revoke reason')->placeholder('—'),
                        TextEntry::make('revokedBy.name')->label('Revoked by')->placeholder('—'),
                    ]),

                Section::make('Download history')
                    ->schema([
                        RepeatableEntry::make('downloadLogs')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('created_at')->dateTime(),
                                TextEntry::make('status')->badge(),
                                TextEntry::make('denial_reason')->placeholder('—'),
                                TextEntry::make('ip_address')->label('IP')->placeholder('—'),
                            ]),
                    ])
                    ->visible(fn (Entitlement $record): bool => $record->downloadLogs->isNotEmpty()),
            ]);
    }
}
