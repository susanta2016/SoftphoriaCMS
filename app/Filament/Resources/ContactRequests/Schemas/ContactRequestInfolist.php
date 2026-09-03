<?php

namespace App\Filament\Resources\ContactRequests\Schemas;

use App\Enums\ContactRequestStatus;
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
