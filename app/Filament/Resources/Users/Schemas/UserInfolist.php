<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserStatus;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        ImageEntry::make('profile.avatar.path')
                            ->label('Avatar')
                            ->disk('public')
                            ->circular()
                            ->columnSpanFull(),
                        TextEntry::make('name'),
                        TextEntry::make('email')
                            ->label('Email address')
                            ->copyable(),
                        TextEntry::make('profile.phone_number')
                            ->label('Phone number')
                            ->placeholder('—'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => UserStatus::from($state)->getLabel())
                            ->color(fn (string $state): string => UserStatus::from($state)->getColor()),
                        IconEntry::make('email_verified_at')
                            ->label('Email verified')
                            ->boolean(),
                        TextEntry::make('email_verified_at')
                            ->label('Verified at')
                            ->dateTime()
                            ->placeholder('Not verified'),
                        TextEntry::make('roles.name')
                            ->label('Roles')
                            ->badge()
                            ->placeholder('No roles assigned')
                            ->helperText('Managed from the Edit page\'s Role & Access section.'),
                    ]),

                Section::make('Profile')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('profile.bio')
                            ->label('Biography')
                            ->columnSpanFull()
                            ->placeholder('—'),
                        TextEntry::make('profile.address')
                            ->label('Address')
                            ->columnSpanFull()
                            ->placeholder('—'),
                        TextEntry::make('profile.zip_code')
                            ->label('Zip code')
                            ->placeholder('—'),
                    ]),

                Section::make('Record')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),
            ]);
    }
}
