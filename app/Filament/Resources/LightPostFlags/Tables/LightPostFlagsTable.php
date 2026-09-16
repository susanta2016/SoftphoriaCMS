<?php

namespace App\Filament\Resources\LightPostFlags\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * List-only (mirrors App\Filament\Resources\ReviewFlags\Tables\
 * ReviewFlagsTable) — a report is only ever created by
 * FlagGratitudeJournalEntryAction from the public site, never hand-built
 * in the admin panel. The query is already scoped to LightPost::flagged()
 * by LightPostFlagResource::getEloquentQuery(), so every row here has at
 * least one report.
 */
class LightPostFlagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'flags.user'])->withCount('flags'))
            ->columns([
                TextColumn::make('user.name')->label('Entry By')->searchable(),
                TextColumn::make('content')->limit(60)->wrap(),
                TextColumn::make('flags_count')->label('Reports')->badge()->color('danger')->sortable(),
                TextColumn::make('created_at')->label('Submitted')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('flags_count', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No reported entries')
            ->emptyStateDescription('Gratitude Journal entries reported via the 🚩 option on the public site will appear here.')
            ->emptyStateIcon('heroicon-o-flag');
    }
}
