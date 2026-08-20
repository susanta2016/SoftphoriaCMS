<?php

namespace App\Modules\Podcast\Filament\Resources\Podcasts;

use App\Modules\Podcast\Filament\Resources\Podcasts\Pages\CreatePodcast;
use App\Modules\Podcast\Filament\Resources\Podcasts\Pages\EditPodcast;
use App\Modules\Podcast\Filament\Resources\Podcasts\Pages\ListPodcasts;
use App\Modules\Podcast\Filament\Resources\Podcasts\Schemas\PodcastForm;
use App\Modules\Podcast\Filament\Resources\Podcasts\Tables\PodcastsTable;
use App\Modules\Podcast\Models\Podcast;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PodcastResource extends Resource
{
    protected static ?string $model = Podcast::class;

    protected static string|UnitEnum|null $navigationGroup = 'Podcast';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Shows';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMicrophone;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PodcastForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PodcastsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPodcasts::route('/'),
            'create' => CreatePodcast::route('/create'),
            'edit' => EditPodcast::route('/{record}/edit'),
        ];
    }
}
