<?php

namespace App\Filament\Resources\SocialLinks\Schemas;

use App\Enums\MediaCategory;
use App\Filament\Support\Media\MediaPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SocialLinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('label')
                    ->label('Platform')
                    ->required()
                    ->maxLength(255)
                    ->helperText('E.g. Facebook, Instagram, YouTube — shown as the icon\'s accessible name.'),

                MediaPicker::make('icon_media_id', 'Icon', MediaCategory::Image),

                TextInput::make('url')
                    ->label('Profile URL')
                    ->url()
                    ->required()
                    ->maxLength(255),

                TextInput::make('sort_order')
                    ->label('Sort order')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->helperText('Lower numbers appear first.'),

                Toggle::make('is_enabled')
                    ->label('Enabled')
                    ->default(true)
                    ->inline(false),
            ]);
    }
}
