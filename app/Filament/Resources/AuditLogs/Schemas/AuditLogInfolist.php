<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use App\Models\AuditLog;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only — an audit log entry has no mutations available from the
 * admin panel (see AuditLogResource's docblock).
 */
class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Entry')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')->label('When')->dateTime(),
                        TextEntry::make('user.name')->label('Actor')->placeholder('System'),
                        TextEntry::make('action')->badge(),
                        TextEntry::make('entity_type')->label('Entity'),
                        TextEntry::make('entity_id')->label('Entity ID')->placeholder('—'),
                        TextEntry::make('ip_address')->label('IP Address')->placeholder('—'),
                        TextEntry::make('user_agent')->label('User Agent')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Metadata')
                    ->schema([
                        TextEntry::make('metadata')
                            ->hiddenLabel()
                            ->placeholder('—')
                            ->formatStateUsing(function (mixed $state): string {
                                // Filament passes the model's cast array
                                // value through here as-is in some contexts
                                // but as its already-JSON-encoded string in
                                // others — normalize both to pretty JSON.
                                $decoded = is_string($state) ? json_decode($state, true) : $state;

                                return filled($decoded) ? json_encode($decoded, JSON_PRETTY_PRINT) : '—';
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (AuditLog $record): bool => filled($record->metadata)),
            ]);
    }
}
