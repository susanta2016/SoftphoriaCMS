<?php

namespace App\Filament\Clusters\WebsiteSetup\Resources\SocialLinks;

use App\Filament\Clusters\WebsiteSetup;
use App\Filament\Clusters\WebsiteSetup\Resources\SocialLinks\Pages\CreateSocialLink;
use App\Filament\Clusters\WebsiteSetup\Resources\SocialLinks\Pages\EditSocialLink;
use App\Filament\Clusters\WebsiteSetup\Resources\SocialLinks\Pages\ListSocialLinks;
use App\Filament\Clusters\WebsiteSetup\Resources\SocialLinks\Schemas\SocialLinkForm;
use App\Filament\Clusters\WebsiteSetup\Resources\SocialLinks\Tables\SocialLinksTable;
use App\Models\SocialLink;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Footer's social media links (icon + URL), managed independently of the
 * footer-navigation Menu — a social link is an icon+URL pair, not a
 * label+destination nav item, so it doesn't fit MenuItem's shape. Lives
 * under Website Setup alongside Settings/Email Templates rather than as its
 * own top-level sidebar item, since it's footer chrome configuration.
 */
class SocialLinkResource extends Resource
{
    protected static ?string $model = SocialLink::class;

    protected static ?string $cluster = WebsiteSetup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return SocialLinkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SocialLinksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSocialLinks::route('/'),
            'create' => CreateSocialLink::route('/create'),
            'edit' => EditSocialLink::route('/{record}/edit'),
        ];
    }
}
