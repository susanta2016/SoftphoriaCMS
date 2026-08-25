<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProseCollections;

use App\Modules\PoetryProse\Filament\Resources\PoetryProseCollections\Pages\CreatePoetryProseCollection;
use App\Modules\PoetryProse\Filament\Resources\PoetryProseCollections\Pages\EditPoetryProseCollection;
use App\Modules\PoetryProse\Filament\Resources\PoetryProseCollections\Pages\ListPoetryProseCollections;
use App\Modules\PoetryProse\Filament\Resources\PoetryProseCollections\RelationManagers\EntriesRelationManager;
use App\Modules\PoetryProse\Filament\Resources\PoetryProseCollections\Schemas\PoetryProseCollectionForm;
use App\Modules\PoetryProse\Filament\Resources\PoetryProseCollections\Tables\PoetryProseCollectionsTable;
use App\Modules\PoetryProse\Models\PoetryProseCollection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PoetryProseCollectionResource extends Resource
{
    protected static ?string $model = PoetryProseCollection::class;

    protected static string|UnitEnum|null $navigationGroup = 'Poetry/Prose';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Collections';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PoetryProseCollectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PoetryProseCollectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            EntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPoetryProseCollections::route('/'),
            'create' => CreatePoetryProseCollection::route('/create'),
            'edit' => EditPoetryProseCollection::route('/{record}/edit'),
        ];
    }
}
