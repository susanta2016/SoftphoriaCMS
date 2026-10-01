<?php

namespace App\Filament\Resources\UtmLinks;

use App\Filament\Resources\UtmLinks\Pages\CreateUtmLink;
use App\Filament\Resources\UtmLinks\Pages\EditUtmLink;
use App\Filament\Resources\UtmLinks\Pages\ListUtmLinks;
use App\Filament\Resources\UtmLinks\Schemas\UtmLinkForm;
use App\Filament\Resources\UtmLinks\Tables\UtmLinksTable;
use App\Models\UtmLink;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * UTM campaign links per channel (Apple Music, Podcast, Instagram,
 * Facebook, Email, or a custom source), so registrations can be traced to
 * where they came from — see the Acquisition section on each User.
 */
class UtmLinkResource extends Resource
{
    protected static ?string $model = UtmLink::class;

    protected static ?string $navigationLabel = 'UTM Links';

    protected static ?string $modelLabel = 'UTM link';

    protected static ?string $slug = 'utm-links';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return UtmLinkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UtmLinksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUtmLinks::route('/'),
            'create' => CreateUtmLink::route('/create'),
            'edit' => EditUtmLink::route('/{record}/edit'),
        ];
    }
}
