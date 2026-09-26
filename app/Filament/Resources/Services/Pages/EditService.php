<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Support\Seo\SavesSeoMetadata;
use App\Models\Service;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EditService extends EditRecord
{
    use SavesSeoMetadata;

    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Save changes')->formId('form'),
            Action::make('view')
                ->label('View page')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Service $record): string => $record->url(), shouldOpenInNewTab: true)
                ->visible(fn (Service $record): bool => $record->is_published),
            DeleteAction::make()->requiresConfirmation(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillSeo($data, $this->record->seo, 'services/'.$this->record->slug);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->pullSeo($data);
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterSave(): void
    {
        $this->persistSeo($this->record, 'services/'.$this->record->slug);
    }
}
