<?php

namespace App\Filament\Clusters\WebsiteSetup\Resources\SocialLinks\Pages;

use App\Filament\Clusters\WebsiteSetup\Resources\SocialLinks\SocialLinkResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSocialLink extends CreateRecord
{
    protected static string $resource = SocialLinkResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return $data;
    }
}
