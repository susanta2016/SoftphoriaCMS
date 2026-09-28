<?php

namespace App\Filament\Resources\PortfolioItems\Pages;

use App\Filament\Resources\PortfolioItems\PortfolioItemResource;
use App\Filament\Support\Seo\SavesSeoMetadata;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePortfolioItem extends CreateRecord
{
    use SavesSeoMetadata;

    protected static string $resource = PortfolioItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->pullSeo($data);
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->persistSeo($this->record, 'portfolio/'.$this->record->slug);
    }
}
