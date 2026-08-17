<?php

namespace App\Filament\Clusters\WebsiteSetup\Resources\SocialLinks\Pages;

use App\Filament\Clusters\WebsiteSetup\Resources\SocialLinks\SocialLinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSocialLinks extends ListRecords
{
    protected static string $resource = SocialLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Social Link'),
        ];
    }
}
