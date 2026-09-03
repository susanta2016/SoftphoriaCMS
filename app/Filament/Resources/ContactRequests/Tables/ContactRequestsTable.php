<?php

namespace App\Filament\Resources\ContactRequests\Tables;

use App\Enums\ContactRequestStatus;
use App\Filament\Resources\ContactRequests\ContactRequestResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContactRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('subject')->placeholder('—')->limit(40),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ContactRequestStatus $state): string => $state->getLabel())
                    ->color(fn (ContactRequestStatus $state): string => $state->getColor())
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->searchable()
            ->filters([
                SelectFilter::make('status')
                    ->options(ContactRequestStatus::options()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('View Details'),
                    ContactRequestResource::updateAction(),
                    ContactRequestResource::deleteAction(),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->label('Actions'),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No messages yet')
            ->emptyStateDescription('New Contact Us submissions from the public form will appear here.')
            ->emptyStateIcon(Heroicon::OutlinedEnvelope);
    }
}
