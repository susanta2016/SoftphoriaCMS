<?php

namespace App\Filament\Resources\UtmLinks\Pages;

use App\Filament\Resources\UtmLinks\Schemas\UtmLinkForm;
use App\Filament\Resources\UtmLinks\UtmLinkResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateUtmLink extends CreateRecord
{
    protected static string $resource = UtmLinkResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = UtmLinkForm::normalize($data);
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
