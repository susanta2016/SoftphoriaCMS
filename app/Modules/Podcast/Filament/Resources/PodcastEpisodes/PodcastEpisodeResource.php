<?php

namespace App\Modules\Podcast\Filament\Resources\PodcastEpisodes;

use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages\CreatePodcastEpisode;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages\EditPodcastEpisode;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages\ListPodcastEpisodes;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Schemas\PodcastEpisodeForm;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Tables\PodcastEpisodesTable;
use App\Modules\Podcast\Models\PodcastEpisode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PodcastEpisodeResource extends Resource
{
    protected static ?string $model = PodcastEpisode::class;

    protected static string|UnitEnum|null $navigationGroup = 'Podcast';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Episodes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlayCircle;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PodcastEpisodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PodcastEpisodesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPodcastEpisodes::route('/'),
            'create' => CreatePodcastEpisode::route('/create'),
            'edit' => EditPodcastEpisode::route('/{record}/edit'),
        ];
    }
}
