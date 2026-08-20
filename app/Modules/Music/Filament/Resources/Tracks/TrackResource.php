<?php

namespace App\Modules\Music\Filament\Resources\Tracks;

use App\Modules\Music\Filament\Resources\Tracks\Pages\CreateTrack;
use App\Modules\Music\Filament\Resources\Tracks\Pages\EditTrack;
use App\Modules\Music\Filament\Resources\Tracks\Pages\ListTracks;
use App\Modules\Music\Filament\Resources\Tracks\Schemas\TrackForm;
use App\Modules\Music\Filament\Resources\Tracks\Tables\TracksTable;
use App\Modules\Music\Models\Track;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TrackResource extends Resource
{
    protected static ?string $model = Track::class;

    protected static string|UnitEnum|null $navigationGroup = 'Music';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Tracks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlayCircle;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return TrackForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TracksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTracks::route('/'),
            'create' => CreateTrack::route('/create'),
            'edit' => EditTrack::route('/{record}/edit'),
        ];
    }
}
