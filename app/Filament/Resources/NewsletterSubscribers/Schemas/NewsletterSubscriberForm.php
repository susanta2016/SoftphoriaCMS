<?php

namespace App\Filament\Resources\NewsletterSubscribers\Schemas;

use Filament\Forms\Components\DateTimePicker;
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
                        'bounced' => 'Bounced',
                        'complained' => 'Complained',
                    ])
                    ->default('subscribed')
                    ->required()
                    ->helperText('Changing this to "Subscribed" also records the confirmation date below and clears any bounce/complaint reason. "Bounced"/"Complained" addresses never receive newsletter sends — only pick those manually to match a real SES report.'),

                DateTimePicker::make('confirmed_at')
                    ->label('Confirmed at')
                    ->disabled()
                    ->dehydrated(false),

                DateTimePicker::make('ses_event_at')
                    ->label('Last SES event at')
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('ses_event_type')
                    ->label('Last SES event type')
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('ses_event_reason')
                    ->label('Last SES event reason')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }
}
