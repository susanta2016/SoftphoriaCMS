<?php

namespace App\Filament\Resources\Reviews\Schemas;

use App\Models\Review;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only — Approve/Reject are header actions on ViewReview, never a form
 * here. reviewable.title is read via Review::reviewableLabel() rather than
 * a literal relation column, since "reviewable" is polymorphic (Podcast
 * Episode today, Track/InspirationalResource later).
 */
class ReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Review')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('reviewableType')->label('Content Type')->badge()->state(fn (Review $record): string => $record->reviewableType()),
                        TextEntry::make('reviewableLabel')->label('Reviewed Item')->state(fn (Review $record): string => $record->reviewableLabel()),
                        TextEntry::make('user.name')->label('Reviewer'),
                        TextEntry::make('user.email')->label('Reviewer Email')->copyable(),
                        TextEntry::make('rating')->label('Rating')->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state)),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('created_at')->label('Submitted')->dateTime(),
                    ]),

                Section::make('Review Content')
                    ->schema([
                        TextEntry::make('content')->hiddenLabel()->columnSpanFull(),
                    ]),
            ]);
    }
}
