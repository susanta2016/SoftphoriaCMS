<?php

namespace App\Filament\Resources\EmailTemplates\Tables;

use App\Models\EmailTemplate;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // One row per notification_key: every currently seeded key has
            // a `user` variant (see config/email_templates.php), so that
            // variant is the table's representative row. The Edit screen
            // loads and saves the sibling `admin` variant too, when one
            // exists for that key.
            ->query(fn (): Builder => EmailTemplate::query()->where('recipient_type', 'user'))
            ->columns([
                TextColumn::make('notification_key')
                    ->label('Notification')
                    ->formatStateUsing(fn (string $state): string => config("email_templates.{$state}.label", $state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('recipients')
                    ->label('Recipients')
                    ->state(fn (EmailTemplate $record): string => collect(config("email_templates.{$record->notification_key}.recipients", []))
                        ->map(fn (string $recipient): string => ucfirst($recipient))
                        ->join(' + '))
                    ->badge(),
                IconColumn::make('is_enabled')
                    ->label('User Email Enabled')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('notification_key');
    }
}
