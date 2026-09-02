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
 * Episode, Track, PoetryProse). Admin-facing label is "Light Posts &
 * Comments" (see ReviewResource's own docblock) — the underlying class/
 * table name is unchanged.
 *
 * **Client-confirmed reversal (2026-09-02):** no `rating` field in the main
 * section — a comment has none. The separate "Legacy Rating" section below
 * is deliberately `->visible()`-gated to only the handful of pre-existing
 * rows that still carry a real star rating, so it's invisible for every
 * ordinary comment record and never presents rating as an active,
 * ongoing part of this feature — purely historical reference for the rows
 * that predate this reversal.
 */
class ReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Light Post / Comment')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('reviewableType')->label('Content Type')->badge()->state(fn (Review $record): string => $record->reviewableType()),
                        TextEntry::make('reviewableLabel')->label('Reviewed Item')->state(fn (Review $record): string => $record->reviewableLabel()),
                        TextEntry::make('user.name')->label('Submitted By'),
                        TextEntry::make('user.email')->label('Submitter Email')->copyable(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('created_at')->label('Submitted')->dateTime(),
                    ]),

                Section::make('Comment')
                    ->schema([
                        TextEntry::make('content')->hiddenLabel()->columnSpanFull(),
                    ]),

                Section::make('Legacy Rating')
                    ->description('Collected before this became a comment-only feature. Shown for historical reference only — no longer part of the active submission form.')
                    ->schema([
                        TextEntry::make('rating')->label('Rating')->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state)),
                    ])
                    ->visible(fn (Review $record): bool => $record->rating !== null),
            ]);
    }
}
