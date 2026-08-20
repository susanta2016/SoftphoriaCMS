<?php

namespace App\Modules\Podcast\Filament\Resources\Podcasts\Schemas;

use App\Enums\MediaCategory;
use App\Filament\Support\Media\MediaPicker;
use App\Models\Category;
use App\Models\Tag;
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
                Select::make('categoryIds')
                    ->label('Categories')
                    ->options(fn (): array => Category::query()->where('type', 'podcast')->orderBy('name')->pluck('name', 'id')->all())
                    ->multiple()
                    ->searchable()
                    ->createOptionForm([
                        TextInput::make('name')->required()->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug($state ?? ''))),
                        TextInput::make('slug')->required()->maxLength(255),
                    ])
                    ->createOptionUsing(fn (array $data): int => Category::query()->create(['type' => 'podcast', ...$data])->getKey())
                    ->dehydrated(),
                Select::make('tagIds')
                    ->label('Tags')
                    ->options(fn (): array => Tag::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->multiple()
                    ->searchable()
                    ->createOptionForm([
                        TextInput::make('name')->required()->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug($state ?? ''))),
                        TextInput::make('slug')->required()->maxLength(255),
                    ])
                    ->createOptionUsing(fn (array $data): int => Tag::query()->create($data)->getKey())
                    ->dehydrated(),
            ]);
    }
}
