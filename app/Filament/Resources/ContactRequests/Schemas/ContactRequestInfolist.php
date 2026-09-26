<?php

namespace App\Filament\Resources\ContactRequests\Schemas;

use App\Enums\ContactRequestStatus;
use App\Shared\Support\Contact\LeadContext;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only submission content — the only mutations available for a
 * contact request are status/resolution notes, via
 * ContactRequestResource::updateAction() (not shown here, since Filament
 * infolists don't host form actions; it's on the View page header).
 */
class ContactRequestInfolist
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
                        TextEntry::make('subject')->placeholder('—'),
                        TextEntry::make('category')->placeholder('—'),
                        TextEntry::make('created_at')->label('Submitted')->dateTime(),
                    ]),

                Section::make('Message')
                    ->schema([
                        TextEntry::make('message')->hiddenLabel()->columnSpanFull(),
                    ]),

                // Lead identification: where the visitor was and what they
                // clicked (see App\Shared\Support\Contact\LeadContext).
                Section::make('Lead source')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('page_title')->label('Page')->placeholder('—'),
                        TextEntry::make('source')
                            ->label('Form')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): ?string => LeadContext::sourceLabel($state))
                            ->placeholder('—'),
                        TextEntry::make('cta_label')->label('Button clicked')->placeholder('—'),
                        TextEntry::make('page_url')
                            ->label('Page URL')
                            ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                            ->color('primary')
                            ->copyable()
                            ->placeholder('—')
                            ->columnSpan(2),
                        TextEntry::make('referrer')
                            ->label('Arrived from')
                            ->formatStateUsing(fn (?string $state): ?string => $state ? (parse_url($state, PHP_URL_HOST) ?: $state) : null)
                            ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                            ->placeholder('Direct / unknown'),
                        TextEntry::make('ip_address')->label('IP address')->copyable()->placeholder('—'),
                        TextEntry::make('user_agent')->label('Browser')->placeholder('—')->columnSpan(2),
                    ]),

                Section::make('Workflow')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (ContactRequestStatus $state): string => $state->getLabel())
                            ->color(fn (ContactRequestStatus $state): string => $state->getColor()),
                        TextEntry::make('resolution_notes')
                            ->label('Resolution Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
