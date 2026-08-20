<?php

namespace App\Modules\Music\Filament\Resources\Singles;

use App\Modules\Music\Filament\Resources\Singles\Pages\CreateSingle;
use App\Modules\Music\Filament\Resources\Singles\Pages\EditSingle;
use App\Modules\Music\Filament\Resources\Singles\Pages\ListSingles;
use App\Modules\Music\Filament\Resources\Singles\Schemas\SingleForm;
use App\Modules\Music\Filament\Resources\Singles\Tables\SinglesTable;
use App\Modules\Music\Models\Single;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SingleResource extends Resource
{
    protected static ?string $model = Single::class;

    protected static string|UnitEnum|null $navigationGroup = 'Music';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Singles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMusicalNote;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return SingleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SinglesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSingles::route('/'),
            'create' => CreateSingle::route('/create'),
            'edit' => EditSingle::route('/{record}/edit'),
        ];
    }
}
