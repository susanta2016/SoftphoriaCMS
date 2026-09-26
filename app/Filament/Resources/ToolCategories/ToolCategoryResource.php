<?php

namespace App\Filament\Resources\ToolCategories;

use App\Filament\Resources\ToolCategories\Pages\ManageToolCategories;
use App\Models\ToolCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Tools → Categories. Used as the filter chips on /tools; a category only
 * shows publicly while it contains a live tool.
 */
class ToolCategoryResource extends Resource
{
    protected static ?string $model = ToolCategory::class;

    protected static string|UnitEnum|null $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'tool category';

    protected static ?string $pluralModelLabel = 'tool categories';

    protected static ?string $slug = 'tool-categories';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(120)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                        if ($operation === 'create') {
                            $set('slug', Str::slug($state ?? ''));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(120)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique(table: ToolCategory::class, column: 'slug', ignoreRecord: true),
                Textarea::make('description')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('tools'))
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->description(fn (ToolCategory $record): ?string => $record->description),
                TextColumn::make('slug')->color('gray'),
                TextColumn::make('tools_count')->label('Tools')->sortable(),
                TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Tools in this category are kept, but lose their category — a published tool then needs a new one before it can be published again.'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageToolCategories::route('/'),
        ];
    }
}
