<?php

namespace App\Filament\Resources\BlogCategories\Pages;

use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Filament\Support\Seo\SavesSeoMetadata;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditBlogCategory extends EditRecord
{
    use SavesSeoMetadata;

    protected static string $resource = BlogCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->requiresConfirmation(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillSeo($data, $this->record->seo, 'blog/category/'.$this->record->slug);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->pullSeo($data);
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterSave(): void
    {
        $this->persistSeo($this->record, 'blog/category/'.$this->record->slug);
    }
}
