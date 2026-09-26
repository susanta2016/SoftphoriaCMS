<?php

namespace App\Filament\Resources\BlogCategories;

use App\Filament\Resources\BlogCategories\Pages\CreateBlogCategory;
use App\Filament\Resources\BlogCategories\Pages\EditBlogCategory;
use App\Filament\Resources\BlogCategories\Pages\ListBlogCategories;
use App\Filament\Support\Seo\SeoFields;
use App\Models\BlogCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Blog → Categories. Each has an indexable archive page at
 * /blog/category/{slug} whose intro is the description below — a real
 * paragraph there is what makes a category page worth ranking.
 */
class BlogCategoryResource extends Resource
{
    protected static ?string $model = BlogCategory::class;

    protected static string|UnitEnum|null $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'blog category';

    protected static ?string $pluralModelLabel = 'blog categories';

    protected static ?string $slug = 'blog/categories';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        $syncCanonical = fn (?string $slug, Set $set, Get $get) => SeoFields::syncCanonicalUrlIfAuto($set, $get, 'seo.canonical_url', 'seo.canonical_url_is_auto', 'blog/category/'.($slug ?? ''));

        return $schema
            ->columns(1)
            ->components([
                Section::make('Category')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(120)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set, Get $get) use ($syncCanonical): void {
                                if ($operation === 'create') {
                                    $slug = Str::slug($state ?? '');
                                    $set('slug', $slug);
                                    $syncCanonical($slug, $set, $get);
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(120)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->unique(table: BlogCategory::class, column: 'slug', ignoreRecord: true)
                            ->prefix('/blog/category/')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, Set $set, Get $get) => $syncCanonical($state, $set, $get)),
                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->helperText('Shown at the top of the category page and used as its search description. A couple of helpful sentences works best.')
                            ->columnSpanFull(),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                    ]),
                Section::make('SEO')
                    ->collapsible()
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        SeoFields::metaTitle(),
                        SeoFields::metaDescription()->columnSpanFull(),
                        Select::make('seo.robots')
                            ->label('Search engine indexing')
                            ->options(['' => 'Index (default)', 'noindex, follow' => 'Noindex — hide from search results'])
                            ->native(false),
                        ...SeoFields::canonicalUrlFields(
                            'seo.canonical_url',
                            'seo.canonical_url_is_auto',
                            fn (Get $get): string => 'blog/category/'.($get('slug') ?? ''),
                        ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('posts'))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->prefix('/blog/category/')->color('gray'),
                TextColumn::make('posts_count')->label('Posts')->sortable(),
                TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Posts in this category are kept — they just become uncategorized.'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogCategories::route('/'),
            'create' => CreateBlogCategory::route('/create'),
            'edit' => EditBlogCategory::route('/{record}/edit'),
        ];
    }
}
