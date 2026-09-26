<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Support\Seo\SavesSeoMetadata;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateBlogPost extends CreateRecord
{
    use SavesSeoMetadata;

    protected static string $resource = BlogPostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->pullSeo($data);
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->persistSeo($this->record, 'blog/'.$this->record->slug);
    }
}
