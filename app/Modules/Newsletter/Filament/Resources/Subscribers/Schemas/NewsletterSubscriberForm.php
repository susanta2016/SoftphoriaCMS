<?php

namespace App\Modules\Newsletter\Filament\Resources\Subscribers\Schemas;

use App\Modules\Newsletter\Support\NewsletterFeature;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Profile + groups only (groups only while Newsletter Marketing is on). No
 * status field: see NewsletterSubscriberResource::subscriptionActions().
 */
class NewsletterSubscriberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Subscriber')
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit')
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'An address can\'t be changed — add the new one as a separate subscriber.' : null)
                            ->columnSpanFull(),
                        TextInput::make('first_name')->maxLength(100),
                        TextInput::make('last_name')->maxLength(100),
                        Select::make('groups')
                            ->relationship('groups', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Group membership never overrides an unsubscribe or suppression.')
                            ->visible(fn (): bool => NewsletterFeature::enabled())
                            ->columnSpanFull(),
                        TextInput::make('source')
                            ->default('admin')
                            ->maxLength(50)
                            ->helperText('Where this subscriber came from, e.g. admin, event, import.')
                            ->visibleOn('create'),
                    ]),

                Section::make('Additional profile fields')
                    ->collapsed()
                    ->schema([
                        KeyValue::make('profile_fields')
                            ->hiddenLabel()
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->reorderable(false),
                    ]),

                Checkbox::make('consent_confirmed')
                    ->label('This person has agreed to receive the newsletter')
                    ->helperText('Required. The subscriber is created as Active with today as their consent date.')
                    ->accepted()
                    ->visibleOn('create'),
            ]);
    }
}
