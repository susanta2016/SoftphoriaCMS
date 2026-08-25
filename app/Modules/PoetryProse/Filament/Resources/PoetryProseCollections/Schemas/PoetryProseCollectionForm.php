<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProseCollections\Schemas;

use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProseCollection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PoetryProseCollectionForm
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
                    ->afterStateUpdated(function (string $operation, ?string $state, Set $set) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug($state ?? ''));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(table: PoetryProseCollection::class, column: 'slug', ignoreRecord: true)
                    ->helperText('Auto-filled from the title — override it if needed.'),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(PoetryProseStatus::options())
                    ->default(PoetryProseStatus::Draft->value)
                    ->required()
                    ->native(false),
            ]);
    }
}
