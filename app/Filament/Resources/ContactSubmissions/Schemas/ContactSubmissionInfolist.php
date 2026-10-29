<?php

namespace App\Filament\Resources\ContactSubmissions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only — a contact message has no mutations available from the admin
 * panel (no status/workflow, unlike ResourceSubmission).
 */
class ContactSubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Submission')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email')->copyable(),
                        TextEntry::make('phone')->placeholder('—')->copyable(),
                        TextEntry::make('created_at')->label('Submitted')->dateTime(),
                    ]),

                Section::make('Message')
                    ->schema([
                        TextEntry::make('message')->hiddenLabel()->columnSpanFull(),
                    ]),

                Section::make('Source')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ip_address')->label('IP address')->placeholder('Not recorded')->copyable(),
                        TextEntry::make('user_agent')->label('Browser (user agent)')->placeholder('Not recorded'),
                    ]),
            ]);
    }
}
