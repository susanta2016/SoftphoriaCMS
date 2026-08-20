<?php

namespace App\Modules\Podcast\Filament\Resources\Podcasts\Schemas;

use App\Enums\MediaCategory;
use App\Filament\Support\Media\MediaPicker;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PodcastForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, ?string $state, Set $set) => $operation === 'create'
                        ? $set('slug', Str::slug($state ?? ''))
                        : null)
                    ->columnSpanFull(),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(table: Podcast::class, column: 'slug', ignoreRecord: true)
                    ->helperText('Auto-filled from the title — override it if needed.'),
                Select::make('status')
                    ->options(PodcastStatus::options())
                    ->default(PodcastStatus::Draft->value)
                    ->required(),
                Textarea::make('description')
                    ->label('About the Podcast')
                    ->rows(4)
                    ->maxLength(65535)
                    ->columnSpanFull()
                    ->helperText('Shown as the podcast\'s own summary — e.g. the "About the Podcast" panel on the episode list/detail pages.'),
                MediaPicker::make('artwork_media_id', 'Artwork', MediaCategory::Image)
                    ->columnSpanFull(),
            ]);
    }
}
