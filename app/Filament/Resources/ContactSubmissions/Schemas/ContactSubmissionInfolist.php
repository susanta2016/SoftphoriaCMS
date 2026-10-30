<?php

namespace App\Filament\Resources\ContactSubmissions\Schemas;

use App\Models\ContactSubmission;
use App\Models\ContactSubmissionReply;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only — a contact message has no mutations available from the admin
 * panel (no status/workflow, unlike ResourceSubmission). Replies are sent
 * from the page's Reply header action and listed in the Replies section.
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

                // Shown whenever replies exist, even with the Reply flag off —
                // turning the feature off never hides history already recorded.
                Section::make('Replies')
                    ->description('"Sent" means the mail server accepted the reply — not confirmed inbox delivery.')
                    ->schema([
                        RepeatableEntry::make('replies')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('sentBy.name')->label('Sent by')->placeholder('Deleted user'),
                                TextEntry::make('created_at')
                                    ->label('Date')
                                    ->state(fn (ContactSubmissionReply $record) => $record->sent_at ?? $record->failed_at ?? $record->created_at)
                                    ->dateTime(),
                                TextEntry::make('status')->badge(),
                                TextEntry::make('recipient_email')->label('To'),
                                TextEntry::make('subject')->columnSpanFull(),
                                TextEntry::make('message')
                                    ->label('Reply')
                                    ->extraAttributes(['class' => 'whitespace-pre-line'])
                                    ->columnSpanFull(),
                            ])
                            ->columns(4),
                    ])
                    ->visible(fn (ContactSubmission $record): bool => $record->replies->isNotEmpty()),

                Section::make('Source')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ip_address')->label('IP address')->placeholder('Not recorded')->copyable(),
                        TextEntry::make('user_agent')->label('Browser (user agent)')->placeholder('Not recorded'),
                    ]),
            ]);
    }
}
