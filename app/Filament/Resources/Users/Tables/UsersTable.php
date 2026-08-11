<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserStatus;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => UserStatus::from($state)->getLabel())
                    ->color(fn (string $state): string => UserStatus::from($state)->getColor())
                    ->sortable(),
                IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(',')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(UserStatus::options()),
                TernaryFilter::make('email_verified_at')
                    ->label('Email verified')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('email_verified_at'),
                        false: fn ($query) => $query->whereNull('email_verified_at'),
                    ),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('View Details'),
                    EditAction::make(),
                    UserResource::blockAction(),
                    UserResource::deleteUserAction(),
                    UserResource::changeStatusAction(),
                    UserResource::sendPasswordResetLinkAction(),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->label('Actions'),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25);
    }
}
