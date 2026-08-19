<?php

namespace App\Filament\Resources\NewsletterSubscribers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class NewsletterSubscriberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('name')
                    ->maxLength(255),

                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'subscribed' => 'Subscribed',
                        'unsubscribed' => 'Unsubscribed',
                    ])
                    ->default('subscribed')
                    ->required()
                    ->helperText('Changing this to "Unsubscribed" also records the date/time below.'),
            ]);
    }
}
