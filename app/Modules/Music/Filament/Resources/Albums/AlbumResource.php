<?php

namespace App\Modules\Music\Filament\Resources\Albums;

use App\Modules\Music\Filament\Resources\Albums\Pages\CreateAlbum;
use App\Modules\Music\Filament\Resources\Albums\Pages\EditAlbum;
use App\Modules\Music\Filament\Resources\Albums\Pages\ListAlbums;
use App\Modules\Music\Filament\Resources\Albums\RelationManagers\TracksRelationManager;
use App\Modules\Music\Filament\Resources\Albums\Schemas\AlbumForm;
use App\Modules\Music\Filament\Resources\Albums\Tables\AlbumsTable;
use App\Modules\Music\Models\Album;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AlbumResource extends Resource
{
    protected static ?string $model = Album::class;

    protected static string|UnitEnum|null $navigationGroup = 'Music';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Albums';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return AlbumForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AlbumsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAlbums::route('/'),
            'create' => CreateAlbum::route('/create'),
            'edit' => EditAlbum::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            TracksRelationManager::class,
        ];
    }
}
