<?php

namespace App\Filament\Resources\BlogCategories\Pages;

use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Filament\Support\Seo\SavesSeoMetadata;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateBlogCategory extends CreateRecord
{
    use SavesSeoMetadata;

    protected static string $resource = BlogCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->pullSeo($data);
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->persistSeo($this->record, 'blog/category/'.$this->record->slug);
    }
}
