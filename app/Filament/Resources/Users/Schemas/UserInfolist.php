<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserStatus;
use App\Shared\Support\Marketing\UtmParameters;
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

                // First-touch UTM attribution captured at registration
                // (UtmAttribution). Empty for accounts registered without a
                // UTM link, admin-created accounts and pre-existing users.
                Section::make('Acquisition')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('utm_source')
                            ->label('Source')
                            ->formatStateUsing(fn (?string $state): ?string => UtmParameters::sourceLabel($state))
                            ->placeholder('No UTM attribution'),
                        TextEntry::make('utm_medium')
                            ->label('Medium')
                            ->placeholder('—'),
                        TextEntry::make('utm_campaign')
                            ->label('Campaign')
                            ->placeholder('—'),
                        TextEntry::make('utm_content')
                            ->label('Content')
                            ->placeholder('—'),
                        TextEntry::make('utm_term')
                            ->label('Term')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Registered at')
                            ->dateTime(),
                        TextEntry::make('utm_landing_url')
                            ->label('Landing page')
                            ->columnSpanFull()
                            ->placeholder('—'),
                        TextEntry::make('utm_captured_at')
                            ->label('First visit')
                            ->dateTime()
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
