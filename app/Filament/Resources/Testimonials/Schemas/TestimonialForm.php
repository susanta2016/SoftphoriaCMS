<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use App\Enums\MediaCategory;
use App\Filament\Support\Media\MediaPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('designation')
                    ->maxLength(255)
                    ->helperText('Optional. E.g. a job title, role, or location.'),

                Textarea::make('message')
                    ->required()
                    ->rows(5)
                    ->maxLength(5000),

                MediaPicker::make('avatar_media_id', 'Avatar', MediaCategory::Image),

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
