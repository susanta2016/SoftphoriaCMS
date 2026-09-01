<?php

namespace App\Modules\Commerce\Filament\Resources\DownloadLogs\Tables;

use App\Modules\Commerce\Enums\DownloadAccessType;
use App\Modules\Commerce\Enums\DownloadLogStatus;
use App\Modules\Commerce\Models\DownloadLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * §6/§16: "Download Activity / Download Grants Admin" — list only, no
 * View/Edit/Delete (an immutable audit trail). Never renders
 * `access_token_hash` — it isn't even queried here — so there is nothing
 * secret this screen could leak (§16: "do not... expose secure download
 * tokens in the list UI").
 */
class DownloadLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('purchaser')
                    ->label('Purchaser')
                    ->getStateUsing(fn (DownloadLog $record): string => $record->user?->email ?? $record->entitlement?->purchaser_email ?? '—'),
                TextColumn::make('track.title')->label('Track')->placeholder('—'),
                TextColumn::make('item')
                    ->label('Album / Single')
                    ->getStateUsing(function (DownloadLog $record): string {
                        $track = $record->track;

                        return $track?->album?->title ?? $track?->single?->title ?? '—';
                    }),
                TextColumn::make('podcastEpisode.title')->label('Podcast Episode')->placeholder('—'),
                TextColumn::make('access_type')->badge()->placeholder('—'),
                TextColumn::make('status')->badge(),
                TextColumn::make('denial_reason')->placeholder('—'),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user_agent')->toggleable(isToggledHiddenByDefault: true)->limit(40),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(DownloadLogStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()])),
                SelectFilter::make('access_type')->options(collect(DownloadAccessType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()])),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }
}
