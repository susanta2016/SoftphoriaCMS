<?php

namespace App\Filament\Resources\UtmLinks\Pages;

use App\Filament\Resources\UtmLinks\Schemas\UtmLinkForm;
use App\Filament\Resources\UtmLinks\UtmLinkResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUtmLink extends EditRecord
{
    protected static string $resource = UtmLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Save changes')->formId('form'),
            $this->getCancelFormAction(),
            DeleteAction::make()->requiresConfirmation(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = UtmLinkForm::normalize($data);
        $data['updated_by'] = Auth::id();

        return $data;
    }
}
