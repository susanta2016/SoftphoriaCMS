<?php

namespace App\Filament\Resources\BlogPosts;

use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Filament\Resources\BlogPosts\Schemas\BlogPostForm;
use App\Filament\Resources\BlogPosts\Tables\BlogPostsTable;
use App\Models\BlogPost;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Blog → Posts. Public at /blog/{slug} while Published and due (see
 * BlogPost::scopeLive()). SEO is saved to the shared seo_metadata relation
 * by the Create/Edit pages (SavesSeoMetadata).
 */
class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static string|UnitEnum|null $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Posts';

    protected static ?string $modelLabel = 'blog post';

    protected static ?string $slug = 'blog/posts';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return BlogPostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlogPostsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogPosts::route('/'),
            'create' => CreateBlogPost::route('/create'),
            'edit' => EditBlogPost::route('/{record}/edit'),
        ];
    }
}
