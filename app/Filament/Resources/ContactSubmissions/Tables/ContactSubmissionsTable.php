<?php

namespace App\Filament\Resources\ContactSubmissions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * List-only (mirrors ResourceSubmissionsTable) — submissions are created
 * exclusively by SubmitContactFormAction from the public form, never
 * hand-created in the admin panel. The only bulk action is Delete, for
 * clearing spam that slips past the honeypot/throttle.
 */
class ContactSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->placeholder('—'),
                TextColumn::make('message')->limit(60)->wrap(),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->searchable()
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No messages yet')
            ->emptyStateDescription('New Contact Us submissions from the public form will appear here.')
            ->emptyStateIcon('heroicon-o-envelope');
    }
}
