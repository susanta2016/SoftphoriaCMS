<?php

namespace App\Filament\Resources\BlogTags;

use App\Filament\Resources\BlogTags\Pages\ManageBlogTags;
use App\Models\BlogTag;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
 * Blog → Tags. Simple name/slug records managed in a modal. Tags can also be
 * created on the fly from a post's form.
 */
class BlogTagResource extends Resource
{
    protected static ?string $model = BlogTag::class;

    protected static string|UnitEnum|null $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Tags';

    protected static ?string $modelLabel = 'blog tag';

    protected static ?string $slug = 'blog/tags';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(60)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                        if ($operation === 'create') {
                            $set('slug', Str::slug($state ?? ''));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(60)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique(table: BlogTag::class, column: 'slug', ignoreRecord: true)
                    ->prefix('/blog/tag/'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('posts'))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->prefix('/blog/tag/')->color('gray'),
                TextColumn::make('posts_count')->label('Posts')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBlogTags::route('/'),
        ];
    }
}
