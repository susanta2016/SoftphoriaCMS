<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Support\Seo\SavesSeoMetadata;
use App\Models\BlogPost;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EditBlogPost extends EditRecord
{
    use SavesSeoMetadata;

    protected static string $resource = BlogPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Save changes')->formId('form'),
            Action::make('view')
                ->label('View post')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (BlogPost $record): string => $record->url(), shouldOpenInNewTab: true)
                ->visible(fn (BlogPost $record): bool => $record->isLive()),
            DeleteAction::make()->requiresConfirmation(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillSeo($data, $this->record->seo, 'blog/'.$this->record->slug);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->pullSeo($data);
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterSave(): void
    {
        $this->persistSeo($this->record, 'blog/'.$this->record->slug);
    }
}
