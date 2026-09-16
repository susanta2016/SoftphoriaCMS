<?php

namespace App\Filament\Resources\LightPostFlags\Schemas;

use App\Models\LightPost;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only — Dismiss Reports/Delete Entry are header actions on
 * ViewLightPostFlag, never a form here. Mirrors App\Filament\Resources\
 * Reviews\Schemas\ReviewInfolist's shape, but standalone (no existing
 * admin LightPost resource to reuse an infolist from — Gratitude Journal
 * entries are otherwise never moderated in the admin panel at all).
 */
class LightPostFlagInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Gratitude Journal Entry')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.name')->label('Entry By'),
                        TextEntry::make('user.email')->label('Author Email')->copyable(),
                        TextEntry::make('created_at')->label('Submitted')->dateTime(),
                    ]),

                Section::make('Entry')
                    ->schema([
                        TextEntry::make('content')->hiddenLabel()->columnSpanFull(),
                    ]),

                Section::make('Reports')
                    ->description('Members who 🚩 reported this entry. Resolve reports from Community → Flagged Journal Entries.')
                    ->schema([
                        RepeatableEntry::make('flags')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('user.name')->label('Reported By'),
                                TextEntry::make('created_at')->label('Reported')->dateTime(),
                            ])
                            ->columns(2),
                    ])
                    ->visible(fn (LightPost $record): bool => $record->flags->isNotEmpty()),
            ]);
    }
}
