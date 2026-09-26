<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Support\Seo\SavesSeoMetadata;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateService extends CreateRecord
{
    use SavesSeoMetadata;

    protected static string $resource = ServiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->pullSeo($data);
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->persistSeo($this->record, 'services/'.$this->record->slug);
    }
}
